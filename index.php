<?include_once('BoxberryDeliveryCalc.php');?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title> Test Boxberry </title>
</head>
<body>
	<? 
		$box = new Boxberry();
		$price = $box->deliverycost(600,179);
		$city = $box->getListCity();
		$pvz = $box->getCityPVZ(179);
		$point = $box->getFromDelivery();
		// echo '<pre>'.print_r($point,1).'</pre>';
		// echo '<pre>'.print_r($city,1).'</pre>';
		// echo '<pre>'.print_r($pvz,1).'</pre>';
		echo '<pre>'.print_r($price,1).'</pre>';
	?>
</body>
</html>
