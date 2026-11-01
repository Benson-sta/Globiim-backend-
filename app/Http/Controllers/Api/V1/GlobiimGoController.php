<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GlobiimGoController extends Controller
{
    public function dashboard(Request $request)
    {
        $userId = $request->user()->id;
        return response()->json(['data' => [
            'partner_applications' => DB::table('go_partner_applications')->where('user_id',$userId)->count(),
            'driver_profile' => DB::table('go_driver_profiles')->where('user_id',$userId)->first(),
            'vehicles' => DB::table('go_vehicles')->where('owner_user_id',$userId)->count(),
            'rides' => DB::table('go_rides')->where('passenger_user_id',$userId)->orWhere('driver_user_id',$userId)->count(),
            'trip_bookings' => DB::table('go_trip_bookings')->where('passenger_user_id',$userId)->count(),
            'delivery_jobs' => DB::table('go_delivery_jobs')->where('customer_user_id',$userId)->count(),
        ]]);
    }

    public function applyPartner(Request $request)
    {
        $d=$request->validate(['partner_type'=>['required',Rule::in(['sales_agent','driver','fleet_owner','tour_operator','bus_company','courier','truck_operator','corporate'])],
            'full_name'=>['required','string','max:160'],'phone'=>['required','string','max:32'],'territory'=>['nullable','string','max:120'],'details'=>['nullable','array']]);
        $id=DB::table('go_partner_applications')->insertGetId([...$d,'user_id'=>$request->user()->id,'status'=>'submitted','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Partner application submitted for review.','data'=>DB::table('go_partner_applications')->find($id)],201);
    }

    public function applyDriver(Request $request)
    {
        $d=$request->validate(['driver_type'=>['required',Rule::in(['independent','managed_fleet'])],'licence_number'=>['required','string','max:100'],
            'licence_expiry'=>['nullable','date'],'service_categories'=>['required','array','min:1'],'service_categories.*'=>['string','max:40'],'documents'=>['nullable','array']]);
        $id=DB::table('go_driver_profiles')->updateOrInsert(['user_id'=>$request->user()->id],
            [...$d,'verification_status'=>'pending','availability'=>'offline','updated_at'=>now(),'created_at'=>now()]);
        return response()->json(['message'=>'Driver application saved and awaiting verification.']);
    }

    public function createVehicle(Request $request)
    {
        $d=$request->validate(['registration_number'=>['required','string','max:32','unique:go_vehicles,registration_number'],
            'vehicle_type'=>['required','string','max:40'],'make'=>['nullable','string','max:80'],'model'=>['nullable','string','max:80'],
            'year'=>['nullable','integer','min:1980','max:'.(now()->year+1)],'seat_capacity'=>['required','integer','min:1','max:100'],
            'photos'=>['nullable','array'],'documents'=>['nullable','array'],'insurance_expiry'=>['nullable','date'],'inspection_expiry'=>['nullable','date']]);
        $id=DB::table('go_vehicles')->insertGetId([...$d,'owner_user_id'=>$request->user()->id,'verification_status'=>'pending','operational_status'=>'inactive','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Vehicle registered and awaiting document review.','data'=>DB::table('go_vehicles')->find($id)],201);
    }

    public function requestFleetManagement(Request $request)
    {
        $d=$request->validate(['management_type'=>['required',Rule::in(['full_management','driver_management','self_managed','fleet_investment'])],
            'terms'=>['nullable','array']]);
        $id=DB::table('go_fleet_agreements')->insertGetId([...$d,'owner_user_id'=>$request->user()->id,'status'=>'requested','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Fleet management request submitted. No management agreement is active until reviewed and signed.','data'=>DB::table('go_fleet_agreements')->find($id)],201);
    }

    public function requestRide(Request $request)
    {
        $d=$request->validate(['service_class'=>['required','string','max:40'],'pickup'=>['required','array'],'pickup.label'=>['required','string','max:250'],
            'destination'=>['required','array'],'destination.label'=>['required','string','max:250'],'scheduled_at'=>['nullable','date','after:now']]);
        $id=DB::table('go_rides')->insertGetId(['reference'=>'GO-RIDE-'.Str::upper(Str::random(10)),
            'passenger_user_id'=>$request->user()->id,'service_class'=>$d['service_class'],'pickup'=>json_encode($d['pickup']),
            'destination'=>json_encode($d['destination']),'status'=>'requested','scheduled_at'=>$d['scheduled_at']??null,
            'currency'=>'KES','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Ride request recorded. Driver matching is not active until dispatch is configured.','data'=>DB::table('go_rides')->find($id)],201);
    }

    public function rides(Request $request)
    {
        return response()->json(['data'=>DB::table('go_rides')->where(fn($q)=>$q->where('passenger_user_id',$request->user()->id)->orWhere('driver_user_id',$request->user()->id))->orderByDesc('created_at')->paginate(20)]);
    }

    public function createTrip(Request $request)
    {
        $d=$request->validate(['title'=>['required','string','max:180'],'description'=>['required','string','max:10000'],
            'route'=>['required','array'],'departure_at'=>['required','date','after:now'],'return_at'=>['nullable','date','after:departure_at'],
            'seat_capacity'=>['required','integer','min:1','max:100'],'price_per_seat'=>['required','numeric','min:0'],
            'trip_category'=>['required','string','max:40'],'media'=>['nullable','array'],'compliance'=>['nullable','array']]);
        $id=DB::table('go_trips')->insertGetId([...$d,'organiser_user_id'=>$request->user()->id,
            'seats_available'=>$d['seat_capacity'],'currency'=>'KES','status'=>'draft',
            'route'=>json_encode($d['route']),'media'=>isset($d['media'])?json_encode($d['media']):null,
            'compliance'=>isset($d['compliance'])?json_encode($d['compliance']):null,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Trip saved as a draft. Compliance approval is required before publishing commercial trips.','data'=>DB::table('go_trips')->find($id)],201);
    }

    public function trips(Request $request)
    {
        $q=DB::table('go_trips')->where('status','published')->where('departure_at','>',now());
        if($request->filled('category'))$q->where('trip_category',$request->string('category')->toString());
        return response()->json(['data'=>$q->orderBy('departure_at')->paginate(20)]);
    }

    public function publishTrip(Request $request,int $trip)
    {
        $r=DB::table('go_trips')->where('id',$trip)->where('organiser_user_id',$request->user()->id)->first();
        if(!$r)return response()->json(['message'=>'Trip not found.'],404);
        if(empty($r->compliance))return response()->json(['message'=>'Add vehicle capacity, insurance and applicable licence compliance information before requesting publication.'],422);
        DB::table('go_trips')->where('id',$trip)->update(['status'=>'pending_review','updated_at'=>now()]);
        return response()->json(['message'=>'Trip submitted for operational review. It is not publicly bookable yet.']);
    }

    public function bookTrip(Request $request,int $trip)
    {
        $d=$request->validate(['seats'=>['required','integer','min:1','max:20']]);
        $booking=DB::transaction(function()use($request,$trip,$d){
            $t=DB::table('go_trips')->where('id',$trip)->lockForUpdate()->first();
            if(!$t||$t->status!=='published')abort(404,'Published trip not found.');
            if($t->organiser_user_id===$request->user()->id)abort(422,'Organisers cannot book their own trip.');
            if($t->seats_available<$d['seats'])abort(422,'Not enough seats remain.');
            $existing=DB::table('go_trip_bookings')->where('trip_id',$trip)->where('passenger_user_id',$request->user()->id)->first();
            if($existing)abort(422,'You already have a booking for this trip.');
            DB::table('go_trips')->where('id',$trip)->decrement('seats_available',$d['seats']);
            $id=DB::table('go_trip_bookings')->insertGetId(['trip_id'=>$trip,'passenger_user_id'=>$request->user()->id,
                'seats'=>$d['seats'],'total_amount'=>$t->price_per_seat*$d['seats'],'currency'=>$t->currency,
                'status'=>'payment_pending','created_at'=>now(),'updated_at'=>now()]);
            return DB::table('go_trip_bookings')->find($id);
        });
        return response()->json(['message'=>'Seats temporarily reserved. Booking remains unpaid until payment is confirmed.','data'=>$booking],201);
    }

    public function createDelivery(Request $request)
    {
        $d=$request->validate(['job_type'=>['required',Rule::in(['boda','express','parcel','van','furniture','truck','cargo','multi_stop','scheduled'])],
            'pickup'=>['required','array'],'dropoff'=>['required','array'],'package_details'=>['nullable','array']]);
        $id=DB::table('go_delivery_jobs')->insertGetId([...$d,'customer_user_id'=>$request->user()->id,'status'=>'requested','currency'=>'KES','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Delivery request recorded. Provider matching is not yet connected.','data'=>DB::table('go_delivery_jobs')->find($id)],201);
    }

    public function createVehicleTransport(Request $request)
    {
        $d=$request->validate(['vehicle_details'=>['required','array'],'pickup'=>['required','array'],'dropoff'=>['required','array'],
            'photos'=>['nullable','array'],'preferred_date'=>['nullable','date','after_or_equal:today']]);
        $id=DB::table('go_vehicle_transports')->insertGetId([...$d,'customer_user_id'=>$request->user()->id,
            'status'=>'quote_requested','currency'=>'KES','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Vehicle transport quote requested.','data'=>DB::table('go_vehicle_transports')->find($id)],201);
    }

    public function createCorporateAccount(Request $request)
    {
        $d=$request->validate(['company_name'=>['required','string','max:180'],'registration_number'=>['nullable','string','max:100'],
            'offices'=>['nullable','array'],'settings'=>['nullable','array']]);
        $id=DB::table('go_corporate_accounts')->insertGetId([...$d,'owner_user_id'=>$request->user()->id,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Corporate account submitted for verification.','data'=>DB::table('go_corporate_accounts')->find($id)],201);
    }

    public function reportIncident(Request $request)
    {
        $d=$request->validate(['incident_type'=>['required','string','max:40'],'reference'=>['nullable','string','max:100'],
            'description'=>['required','string','max:10000'],'location'=>['nullable','array'],'evidence'=>['nullable','array'],
            'priority'=>['nullable',Rule::in(['low','normal','high','urgent'])]]);
        $id=DB::table('go_safety_incidents')->insertGetId([...$d,'reporter_user_id'=>$request->user()->id,
            'priority'=>$d['priority']??'normal','status'=>'open','location'=>isset($d['location'])?json_encode($d['location']):null,
            'evidence'=>isset($d['evidence'])?json_encode($d['evidence']):null,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Incident report received. This endpoint does not dispatch emergency services.','data'=>DB::table('go_safety_incidents')->find($id)],201);
    }
}
