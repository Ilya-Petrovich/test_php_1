<?php

function createOrder($event_id, $event_date, $ticket_adult_price, 
	$ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity) {
	$conn = new mysqli("localhost", "root", "", "test_db");
	$user_id = 143;

	if ($conn->connect_error){
		die("Error: " . $conn->connect_error);
	}

	$equal_price = $ticket_adult_price * $ticket_adult_quantity + $ticket_kid_price * $ticket_kid_quantity;

	$url = 'https://api.site.com/book';
	// $url = 'http://localhost/test_tasks/nevatrip_02.11.24/mok_script.php';

	# making 3 attempts to avoid potential problems with infinite loop
	$n = 3;

	for ($i = 0; $i < $n; $i++) {
		# generating barcode
		$barcode = rand(10000000, 99999999);

		# preparing POST request to API
		$data = ['event_id' => $event_id, 'event_date' => $event_date,
				'ticket_adult_price' => $ticket_adult_price, 'ticket_adult_quantity' => $ticket_adult_quantity,
				'ticket_kid_price' => $ticket_kid_price, 'ticket_kid_quantity' => $ticket_kid_quantity,
				'barcode' => $barcode];
		$options = [
			'http' => [
				'header' => "Content-type: application/x-www-form-urlencoded\r\n",
				'method' => 'POST',
				'content' => http_build_query($data),
			],
		];

		$context = stream_context_create($options);
		$result = file_get_contents($url, false, $context);

		# handle API responses
		if ($result == "{error: 'barcode already exists'}") {
			print("Barcode already exists! ");
			
			if ($i < $n - 1) {
				print("Regenerating barcode...<br>");
			} else {
				exit("Warning: All attempts failed!");
			}
			
			continue;
		} else if ($result == "{message: 'order successfully booked'}") {
			print("Order successfully booked.<br>");
			break;
		} else {
			exit("Error: Create order error!");
		}
	}
	
	# if we received success respond for create request we make approve request
	$url = 'https://api.site.com/approve';
	// $url = 'http://localhost/test_tasks/nevatrip_02.11.24/mok_script_2.php';

	$data = ['barcode' => $barcode];
	$options = [
		'http' => [
			'header' => "Content-type: application/x-www-form-urlencoded\r\n",
			'method' => 'POST',
			'content' => http_build_query($data),
		],
	];

	$context = stream_context_create($options);
	$result = file_get_contents($url, false, $context);

	# I assumed there must be "approved" instead of "aproved" so I fixed typo
	# if we received success respond for approve request we save order to db
	if ($result == "{message: 'order successfully approved'}") {
		$sql = "INSERT INTO orders (event_id, event_date, ticket_adult_price, ticket_adult_quantity, 
		ticket_kid_price, ticket_kid_quantity, barcode, user_id, equal_price) VALUES ($event_id,
		'$event_date', $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, 
		$ticket_kid_quantity, $barcode, $user_id, $equal_price)";

		if ($conn->query($sql)) {
			print("Data successfully added to db");
		} else {
			print("Error: " . $conn->error);
		}
	} else {
		exit("Error: Approve order error!");
	}
		
	$conn->close();
}

createOrder(3, date("Y-m-d H:i:s"), 900, 2, 550, 3);
?>
