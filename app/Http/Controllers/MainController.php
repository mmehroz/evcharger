<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;
use Image;
use DB;
use Input;
use App\Item;
use Session;
use Response;
use Validator;
use URL;
use WebSocket\Client;

class MainController extends Controller
{
    public function stationList(Request $request){
        $getdata = DB::table('station')
        ->select('*')
        ->where('status_id','=',1)
        ->paginate(30);
        $stations_image_path = URL::to('/')."/public/station_image/";
        if($getdata){
            return response()->json(['data' => $getdata, 'stations_image_path' => $stations_image_path, 'message' => 'Station List'],200);
        }else{
            $emptyarray = array();
            return response()->json(['data' => $emptyarray,'message' => 'Station Not Found'],200);
        }
    }
    public function chargerlist(Request $request){
        $validate = Validator::make($request->all(), [ 
          'station_id'       => 'required',
        ]);
        if ($validate->fails()) {    
            return response()->json("Select Station", 400);
        }
        $getdata = DB::table('charger')
        ->select('*')
        ->where('station_id','=',$request->station_id)
        ->where('status_id','=',1)
        ->paginate(30);
        $chargers_image_path = URL::to('/')."/public/station_image/";
        if($getdata){
            return response()->json(['data' => $getdata, 'chargers_image_path' => $chargers_image_path ,'message' => 'Charger List'],200);
        }else{
            $emptyarray = array();
            return response()->json(['data' => $emptyarray,'message' => 'Charger Not Found'],200);
        }
    }
     public function startcharging(Request $request){
        $validate = Validator::make($request->all(), [ 
          'station_id'          => 'required',
          'charger_id'          => 'required',
          'priceunit_id'        => 'required',
        ]);
        if ($validate->fails()) {    
            return response()->json("Fields Required", 400);
        }
        $save = DB::table('charginglog')->insert([
            'charginglog_starttime' => date('Y-m-d h:i:s'),
            'priceunit_id'          => $request->priceunit_id,
            'charginglog_type'      => 0,
            'station_id'            => $request->station_id,
            'charger_id'            => $request->charger_id,
            'status_id'             => 1,
            'created_at'            => date('Y-m-d h:i:s'),
        ]);
        $charginglog_id  = DB::getPdo()->lastInsertId();
        if($save){
            return response()->json(['charginglog_id' => $charginglog_id, 'message' => 'Charging Start Successfully'],200);
        }else{
            return response()->json("Oops! Something Went Wrong", 400);
        }
    }
     public function stopcharging(Request $request){
        $validate = Validator::make($request->all(), [ 
          'charginglog_id'   => 'required',
        ]);
        if ($validate->fails()) {    
            return response()->json("Fields Required", 400);
        }
        $getlog = DB::table('charginglog')
        ->select('priceunit_id','charger_id','charginglog_starttime')
        ->where('charginglog_id','=',$request->charginglog_id)
        ->first();
        $starttime = strtotime($getlog->charginglog_starttime);
        $stoptime = strtotime(date('Y-m-d h:i:s'));
        $timeduration = round(abs($starttime - $stoptime) / 60,2);
        if ($getlog->priceunit_id == 1) {
            $getprice = DB::table('charger')
            ->select('charger_price')
            ->where('charger_id','=',$getlog->charger_id)
            ->first();
            $totalprice = $getprice->charger_price*$timeduration;
        }else{
            $getprice = DB::table('charger')
            ->select('charger_pricekwh')
            ->where('charger_id','=',$getlog->charger_id)
            ->first();
            $totalprice = $getprice->charger_pricekwh*$timeduration;
        }
        $adds = [
            'charginglog_stoptime'  => date('Y-m-d h:i:s'),
            'charginglog_type'      => 1,
            'charginglog_price'     => $totalprice,
            'updated_at'            => date('Y-m-d h:i:s'),
        ];
        $save = DB::table('charginglog')
        ->where('charginglog_id','=',$request->charginglog_id)
         ->update($adds);
        if($save){
            return response()->json(['totalprice' => $totalprice, 'message' => 'Charging Stop Successfully'],200);
        }else{
            return response()->json("Oops! Something Went Wrong", 400);
        }
    }
    public function priceunitlist(Request $request){
        $getdata = DB::table('priceunit')
        ->select('*')
        ->get(30);
        $chargers_image_path = URL::to('/')."/public/station_image/";
        if($getdata){
            return response()->json(['data' => $getdata, 'message' => 'Price Unit List'],200);
        }else{
            $emptyarray = array();
            return response()->json(['data' => $emptyarray,'message' => 'Not Found'],200);
        }
    }
    public function chargerstatus(Request $request){
        $validate = Validator::make($request->all(), [ 
          'station_id'          => 'required',
          'charger_id'          => 'required',
        ]);
        if ($validate->fails()) {    
            return response()->json("Fields Required", 400);
        }
        $getdetail = DB::table('charger')
        ->select('*')
        ->where('station_id','=',$request->station_id)
        ->where('charger_id','=',$request->charger_id)
        ->first();
        $getstatus = DB::connection('mysql2')->table('activeCS')
        ->select('*')
        ->where('csName','=',$getdetail->charger_name)
        ->first();
        if($getstatus){
            return response()->json(['data' => $getstatus, 'message' => 'Charger Status'],200);
        }else{
            $emptyarray = array();
            return response()->json(['data' => $emptyarray,'message' => 'Not Found'],200);
        }
    }
     public function websocket(){
        // set_time_limit(0);
        // ini_set("default_socket_timeout", '-1');

        // define('HOST_NAME',"127.0.0.1");
        // define('PORT',"8080");
        // $null = NULL;

        // require_once("class.MsgHandler.php");
        // $msgHandler = new MsgHandler();

        // require_once("class.mongoDB.php");
        // $mongoDB = new ocppMongo();

        // $socketResource = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        // socket_set_option($socketResource, SOL_SOCKET, SO_REUSEADDR, 1);
        // socket_bind($socketResource, 0, PORT);
        // socket_listen($socketResource);

        // $csArray = array();
        // $clientSocketArray = array($socketResource);
        // while (true) {
        //         $newSocketArray = $clientSocketArray;
        //         socket_select($newSocketArray, $null, $null, 0, 10);

        //         if (in_array($socketResource, $newSocketArray)) {
        //                 $newSocket = socket_accept($socketResource);
        //                 $clientSocketArray[] = $newSocket;

        //                 $header = socket_read($newSocket, 1024);
        //                 socket_getpeername($newSocket, $client_ip_address);

        //                 $chargeStation = $msgHandler->validateRequestURL($header, $client_ip_address);

        //                 $msgHandler->logHeaders($client_ip_address, $header);

        //                 if(empty($chargeStation) || (strpos($chargeStation, '/') !== false)) {
        //                         $connectionACK = $msgHandler->connectionDisconnectACK($client_ip_address);
        //                 }else {
        //                         $msgHandler->doHandshake($header, $newSocket, HOST_NAME, PORT);
        //                         $connectionACK = $msgHandler->newConnectionACK($client_ip_address);
        //                         $mongoDB->updateStatus($chargeStation, 'ON');
        //                         $csArray["$newSocket"] = $chargeStation;
        //                 }

        //                 $msgHandler->send($connectionACK, $newSocket);

        //                 $newSocketIndex = array_search($socketResource, $newSocketArray);
        //                 unset($newSocketArray[$newSocketIndex]);
        //         }

        //         foreach ($newSocketArray as $newSocketArrayResource) {
        //                 while(socket_recv($newSocketArrayResource, $socketData, 1024, 0) >= 1){
        //                         $socketMessage = $msgHandler->unseal($socketData);
        //                         $messageObj = json_decode($socketMessage);

        //                         $csName = $csArray["$newSocketArrayResource"];

        //                         $msgHandler->logMsg($client_ip_address, $socketMessage, $csName);

        //                         // echo "chargeStation". $chargeStation;
        //                         // print_r($messageObj);

        //                         $jsonResponse = $msgHandler->processMsg($client_ip_address, $csName, $messageObj, $mongoDB);

        //                         $finalMsg = $msgHandler->logRespMsg($client_ip_address, $socketMessage, $csName, $jsonResponse                                                                               );

        //                         //$chat_box_message = $msgHandler->createChatBoxMessage($messageObj->chat_user, $messageObj->c                                                                               hat_message);
        //                         $msgHandler->send($finalMsg, $newSocketArrayResource);
        //                         break 2;
        //                 }

        //                 $socketData = @socket_read($newSocketArrayResource, 1024, PHP_NORMAL_READ);
        //                 if ($socketData === false) {
        //                         socket_getpeername($newSocketArrayResource, $client_ip_address);
        //                         $connectionACK = $msgHandler->connectionDisconnectACK($client_ip_address);
        //                         $msgHandler->send($connectionACK, $newSocketArrayResource);
        //                         $newSocketIndex = array_search($newSocketArrayResource, $clientSocketArray);
        //                         unset($clientSocketArray[$newSocketIndex]);
        //                         $csName = $csArray["$newSocketArrayResource"];
        //                         $mongoDB->updateStatus($csName, 'OFF');
        //                 }
        //         }
        // }
        // socket_close($socketResource);

        // $client = new \WebSocket\Client("ws://localhost:8080/webservice/ocpp/CS123");
        //     try {
        //         $message = $client->receive();
        //         print_r($message);
        //         echo "\n";
         
        //       } catch (\WebSocket\ConnectionException $e) {
        //         // Possibly log errors
        //         print_r("Error: ".$e->getMessage());
        //     }
        // while (true) {
        //     try {
        //         $message = $client->receive();
        //         print_r($message);
        //         echo "\n";
         
        //       } catch (\WebSocket\ConnectionException $e) {
        //         // Possibly log errors
        //         print_r("Error: ".$e->getMessage());
        //     }
        // }
        // $client->close();

        $host = 'localhost';  //where is the websocket server
        $port = 8080;
        $local = "ws://localhost:8080/webservice/ocpp/CS12";  //url where this script run
        $data = '[2,"opscs62b57e2bc9da0","RemoteStartTransaction",{"connectorId":1,"idTag":"0202200220200010"}]';  //data to be send
        $head = "GET / HTTP/1.1"."\r\n".
                    "Upgrade: WebSocket"."\r\n".
                    "Connection: Upgrade"."\r\n".
                    "Origin: $local"."\r\n".
                    "Host: $host"."\r\n".
                    "Content-Length: ".strlen($data)."\r\n"."\r\n";
        //WebSocket handshake
        $sock = fsockopen($host, $port, $errno, $errstr, 2);
        fwrite($sock, $head ) or die('error:'.$errno.':'.$errstr);
        $headers = fread($sock, 2000);
        fwrite($sock, "\x00$data\xff" ) or die('error:'.$errno.':'.$errstr);
        $wsdata = fread($sock, 2000);  //receives the data included in the websocket package "\x00DATA\xff"
        fclose($sock);
        $port_number    = 8080;
        $IPadress_host    = "103.133.133.19";
        $hello_msg= "This is server";
        //  echo "Hitting the server :".$hello_msg;
        $socket_creation = socket_create(AF_INET, SOCK_STREAM, 0) or die("Unable to create connection with socket\n");
        $server_connect = socket_connect($socket_creation, $IPadress_host , $port_number) or die("Unable to create connection with server\n");
        socket_write($socket_creation, $hello_msg, strlen($hello_msg)) or die("Unable to send data to the  server\n");
        $server_connect = socket_read ($socket_creation, 1024) or die("Unable to read response from the server\n");
        echo $server_connect;
        socket_close($socket_creation);

      // set some variables
            // $host = "localhost";
            // $port = 8080;
            // // don't timeout!
            // // set_time_limit(0);
            // // create socket
            // $socket = socket_create(AF_INET, SOCK_STREAM, 0) or die("Could not create socket\n");
            // // bind socket to port
            // $result = socket_bind($socket, $host, $port) or die("Could not bind to socket\n");
            // // start listening for connections
            // $result = socket_listen($socket, 3) or die("Could not set up socket listener\n");
            // // accept incoming connections
            // // spawn another socket to handle communication
            // $spawn = socket_accept($socket) or die("Could not accept incoming connection\n");
            // // read client input
            // $input = socket_read($spawn, 1024) or die("Could not read input\n");
            // // clean up input string
            // $input = trim($input);
            // echo "Client Message : ".$input;
            // // reverse client input and send back
            // $output = strrev($input) . "\n";
            // socket_write($spawn, $output, strlen ($output)) or die("Could not write output\n");
            // // close sockets
            // socket_close($spawn);
            // socket_close($socket);

            // $host    = "localhost";
            // $port    = 8080;
            // // $message = "Hello Server";
            // // echo "Message To server :".$message;
            // // create socket
            // $socket = socket_create(AF_INET, SOCK_STREAM, 0) or die("Could not create socket\n");
            // // connect to server
            // $result = socket_connect($socket, $host, $port) or die("Could not connect to server\n");  
            // // send string to server
            // // socket_write($socket, $message, strlen($message)) or die("Could not send data to server\n");
            // // get server response
            // $result = socket_read ($socket, 1024) or die("Could not read server response\n");
            // echo "Reply From Server  :".$result;
            // // close socket
            // socket_close($socket);

        // $sock = stream_socket_client("ws://localhost:8080/webservice/ocpp/CS12",$error,$errnum,30,STREAM_CLIENT_CONNECT,stream_context_create(null));
        // if (!$sock) {
        //     echo "[$errnum] $error" . PHP_EOL;
        // } else {
        //   echo "Connected - Do NOT get rekt!" . PHP_EOL;
        //   fwrite($sock, "GET /stream?streams=btcusdt@kline_1m HTTP/1.1\r\nHost: stream.binance.com:9443\r\nAccept: */*\r\nConnection: Upgrade\r\nUpgrade: websocket\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Key: ".rand(0,999)."\r\n\r\n");
        //   while (!feof($sock)) {
        //     var_dump(explode(",",fgets($sock, 512)));
        //   }
        // }
        
        // error_reporting(E_ALL);
        // /* Get the port for the WWW service. */
        // $service_port = '8080';

        // /* Get the IP address for the target host. */
        // $address = gethostbyname('localhost');

        // /* Create a TCP/IP socket. */
        // $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        // if ($socket === false) {
        //     echo "socket_create() failed: reason: " . 
        //          socket_strerror(socket_last_error()) . "\n";
        // }

        // echo "Attempting to connect to '$address' on port '$service_port'...";
        // $result = socket_connect($socket, $address, $service_port);
        // if ($result === false) {
        //     echo "socket_connect() failed.\nReason: ($result) " . 
        //           socket_strerror(socket_last_error($socket)) . "\n";
        // }

        // $in = "HEAD / HTTP/1.1\r\n";
        // $in .= "Host: localhost\r\n";
        // $in .= "Connection: Close\r\n\r\n";
        // $out = '';

        // echo "Sending HTTP HEAD request...";
        // socket_write($socket, $in, strlen($in));
        // echo "OK.\n";

        // echo "Reading response:\n\n";
        // while ($out = socket_read($socket, 2048)) {
        //     echo $out;
        // }

        // socket_close($socket);
    }
}
