FROM golang:1.23-alpine AS build
WORKDIR /src
COPY go.mod ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /out/messaging .
FROM alpine:3.20
RUN adduser -D -H -u 10001 app
COPY --from=build /out/messaging /usr/local/bin/messaging
USER app
EXPOSE 8081
ENTRYPOINT ["/usr/local/bin/messaging"]
