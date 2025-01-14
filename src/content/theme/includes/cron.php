<?php

function cron_check_visa_dates() {
	$dealer = 1; //1015 gaziantep

	$dealers = array();
	$dealers["1"] = "İstanbul";
	$dealers["1015"] = "Gaziantep";

	$total_slots = 0;

    $slots = array();

    // Yarından itibaren 1 ay boyunca her gün için request yapalım
    for ($i = 0; $i < 30; $i++) {

        // Hesaplanan tarihleri al
        $date = date('Y-m-d', strtotime('tomorrow + ' . $i . ' days'));
        $date_formatted = date('d F Y', strtotime('tomorrow + ' . $i . ' days'));

        // API URL oluştur
        $apiUrl = "https://api.kosmosvize.com.tr/api/AppointmentTime_Days/Get?Date={$date}&TypeId=16&DealerId={$dealer}";

        // API'ye request yap
        $response = file_get_contents($apiUrl);

        // JSON'u decode et
        $data = json_decode($response, true);

        // Eğer boş bir yanıt alındıysa devam et
        if (empty($data)) {
            continue;
        }

        $slot = array(
	        "url"  => $apiUrl,
	        "date" => $date_formatted,
	        "hours" => array()
	    );

        // Her bir item için işlemleri gerçekleştir
        foreach ($data as $item) {
            //$formattedDate = date('d F Y', strtotime($item['date']));
            $appointmentTime = $item['appointmentHour']['name'];
            $slot["hours"][] = $appointmentTime;
            $total_slots++;
        }

        $slots[] = $slot;
    }   

    if(count($slots)>0){
    	//$encrypt = new Encrypt();
        //$slots_encrypted = $encrypt->encrypt($slots);

        $visa_slots = get_option("visa_slots");
        if(empty($visa_slots) || $visa_slots != $slots){

        	update_option("visa_slots", $slots);

	    	$to = "info@salthareket.com, ceren@fayda.net, ceren@salt-istanbul.com";
	    	$from = "visa@salt-istanbul.com";
	    	$sender = 'From: '.get_option('name').' <'.$from.'>';
	    	$subject = $dealers[$dealer]." Kosmos ".$total_slots." adet açık randevu var.";
	    	
	    	$headers = array();
	    	$headers[] = 'MIME-Version: 1.0';
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$headers[] = "X-Mailer: PHP";
			$headers[] = $sender;

	        $body = "<table style='border:1px solid #ccc;'><tbody>";
	        foreach($slots as $slot){
	        	$body .= "<tr><td style='font-family:arial;color:blue;font-size:18px;font-we,ght:bold;'><a href='".$slot["url"]."' style='color:nlue;text-decoration:none;'>".$slot["date"]."</a></td></tr>";
	        	$body .= "<tr><td>".implode(" - ", $slot["hours"])."</td></tr>";
	        	$body .= "<tr><td><hr></td></tr>";
	        }
	        $body .= "</tbody></table>";

	        wp_mail($to, $subject, $body, $headers);
        
	        // send sms
	        $message = $dealers[$dealer]." Kosmos ". $total_slots." adet açık randevu var";
	    	$phones = array(
	            "+905353584348",
	            "+905369840410"
	    	);
	    	$vars = array(
	          "recipients" => $phones,
	          "content"    => $message
	        );
		    $sms = new Sms($vars);
		    $sms->message();

        }
        
    } 
}
//add_action( 'check_visa_dates', 'cron_check_visa_dates', 10, 0 );


//check verified reservation's date is past
function cron_check_ended_sessions() {
	$salt = new Salt();
	$salt->sessions(["action"=>"set_ended"]);
}
//add_action( 'check_ended_sessions', 'cron_check_ended_sessions', 10, 0 );



//send notification and mail to upcoming session's expert
function cron_check_starting_sessions() {
	$salt = new Salt();
	$salt->sessions(["action"=>"starting_sessions"]);
}
//add_action( 'check_starting_sessions', 'cron_check_starting_sessions', 10, 0 );