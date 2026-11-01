package main

import (
 "crypto/sha256"
 "crypto/subtle"
 "database/sql"
 "encoding/hex"
 "encoding/json"
 "log"
 "net/http"
 "os"
 "strconv"
 "strings"
 "sync"
 "time"

 _ "github.com/go-sql-driver/mysql"
 "github.com/gorilla/websocket"
)

type Client struct { userID int64; conn *websocket.Conn; send chan []byte; room string }
type Hub struct { mu sync.RWMutex; clients map[string]map[*Client]bool }
type Event struct { Type string `json:"type"`; ConversationID int64 `json:"conversation_id"`; UserID int64 `json:"user_id"`; At time.Time `json:"at"` }

var db *sql.DB
var hub = Hub{clients: make(map[string]map[*Client]bool)}
var upgrader = websocket.Upgrader{
 ReadBufferSize: 1024, WriteBufferSize: 1024,
 CheckOrigin: func(r *http.Request) bool {
  origin := r.Header.Get("Origin")
  for _, item := range strings.Split(os.Getenv("ALLOWED_ORIGINS"), ",") {
   if strings.TrimSpace(item) == origin && origin != "" { return true }
  }
  return os.Getenv("ALLOW_ANY_ORIGIN") == "true"
 },
 Subprotocols: []string{"globiim.messaging.v1"},
}

func main() {
 dsn := os.Getenv("DB_DSN")
 if dsn == "" { log.Fatal("DB_DSN is required") }
 var err error
 db, err = sql.Open("mysql", dsn); if err != nil { log.Fatal(err) }
 if err = db.Ping(); err != nil { log.Fatal(err) }
 http.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
  if db.Ping() != nil { http.Error(w, "database unavailable", 503); return }
  w.Header().Set("Content-Type", "application/json")
  _, _ = w.Write([]byte(`{"status":"ok","service":"globiim-messaging-go"}`))
 })
 http.HandleFunc("/ws", websocketHandler)
 addr := os.Getenv("LISTEN_ADDR"); if addr == "" { addr = ":8081" }
 log.Printf("Globiim realtime messaging listening on %s", addr)
 log.Fatal(http.ListenAndServe(addr, http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
  w.Header().Set("X-Content-Type-Options", "nosniff")
  w.Header().Set("Cache-Control", "no-store")
  http.DefaultServeMux.ServeHTTP(w, r)
 })))
}

func websocketHandler(w http.ResponseWriter, r *http.Request) {
 conversationID, err := strconv.ParseInt(r.URL.Query().Get("conversation_id"), 10, 64)
 if err != nil || conversationID < 1 { http.Error(w, "invalid conversation_id", 400); return }
 token := ""
 for _, p := range websocket.Subprotocols(r) { if strings.HasPrefix(p, "bearer.") { token = strings.TrimPrefix(p, "bearer.") } }
 if token == "" { http.Error(w, "missing authentication", 401); return }
 userID, err := authenticateToken(token); if err != nil { http.Error(w, "unauthorized", 401); return }
 if !isParticipant(userID, conversationID) { http.Error(w, "forbidden", 403); return }
 conn, err := upgrader.Upgrade(w, r, http.Header{"Sec-WebSocket-Protocol": []string{"globiim.messaging.v1"}})
 if err != nil { return }
 client := &Client{userID:userID, conn:conn, send:make(chan []byte, 32), room:strconv.FormatInt(conversationID,10)}
 hub.add(client); go client.writePump(); client.readPump(conversationID)
}

func authenticateToken(plain string) (int64, error) {
 parts := strings.SplitN(plain, "|", 2); if len(parts) != 2 { return 0, sql.ErrNoRows }
 id, err := strconv.ParseInt(parts[0],10,64); if err != nil { return 0, sql.ErrNoRows }
 sum := sha256.Sum256([]byte(parts[1])); hash := hex.EncodeToString(sum[:])
 var userID int64; var stored string
 err = db.QueryRow("SELECT tokenable_id, token FROM personal_access_tokens WHERE id = ? AND tokenable_type = ? AND (expires_at IS NULL OR expires_at > NOW())", id, "App\\Models\\User").Scan(&userID,&stored)
 if err != nil { return 0,err }
 if subtle.ConstantTimeCompare([]byte(stored),[]byte(hash)) != 1 { return 0,sql.ErrNoRows }
 return userID,nil
}

func isParticipant(userID, conversationID int64) bool {
 var count int
 err := db.QueryRow("SELECT COUNT(*) FROM conversation_user WHERE user_id = ? AND conversation_id = ?",userID,conversationID).Scan(&count)
 return err == nil && count == 1
}
func (h *Hub) add(c *Client) {
 h.mu.Lock(); defer h.mu.Unlock()
 if h.clients[c.room] == nil { h.clients[c.room] = make(map[*Client]bool) }; h.clients[c.room][c] = true
}
func (h *Hub) remove(c *Client) {
 h.mu.Lock(); defer h.mu.Unlock()
 if room := h.clients[c.room]; room != nil { delete(room,c); if len(room)==0 { delete(h.clients,c.room) } }; close(c.send)
}
func (h *Hub) broadcast(room string, sender *Client, payload []byte) {
 h.mu.RLock(); defer h.mu.RUnlock()
 for client := range h.clients[room] { if client != sender { select { case client.send <- payload: default: } } }
}
func (c *Client) readPump(conversationID int64) {
 defer func(){ hub.remove(c); _ = c.conn.Close() }()
 c.conn.SetReadLimit(4096); _ = c.conn.SetReadDeadline(time.Now().Add(70*time.Second))
 c.conn.SetPongHandler(func(string) error { return c.conn.SetReadDeadline(time.Now().Add(70*time.Second)) })
 for { _, raw, err := c.conn.ReadMessage(); if err != nil { break }
  var incoming struct { Type string `json:"type"` }; if json.Unmarshal(raw,&incoming)!=nil { continue }
  if incoming.Type!="typing.start" && incoming.Type!="typing.stop" { continue }
  event,_ := json.Marshal(Event{Type:incoming.Type,ConversationID:conversationID,UserID:c.userID,At:time.Now().UTC()})
  hub.broadcast(c.room,c,event)
 }
}
func (c *Client) writePump() {
 ticker:=time.NewTicker(25*time.Second); defer func(){ticker.Stop();_ = c.conn.Close()}()
 for { select {
 case message,ok:=<-c.send:
  _ = c.conn.SetWriteDeadline(time.Now().Add(10*time.Second)); if !ok { _ = c.conn.WriteMessage(websocket.CloseMessage,[]byte{}); return }
  if err:=c.conn.WriteMessage(websocket.TextMessage,message);err!=nil{return}
 case <-ticker.C:
  _ = c.conn.SetWriteDeadline(time.Now().Add(10*time.Second)); if err:=c.conn.WriteMessage(websocket.PingMessage,nil);err!=nil{return}
 } }
}
