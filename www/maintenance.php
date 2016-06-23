<?php

header('HTTP/1.1 503 Service Unavailable');
header('Retry-After: 300'); // 5 minutes in seconds

?>
<!DOCTYPE html>
<meta charset="utf-8">
<meta name="robots" content="noindex">
<meta name="generator" content="Nette Framework">

<style>
	body { color: #333; background: white; width: 500px; margin: 100px auto }
	h1 { font: bold 47px/1.5 sans-serif; margin: .6em 0 }
	p { font: 21px/1.5 Georgia,serif; margin: 1.5em 0 }
</style>

<title>Web je momentálně mimo provoz z důvodu údržby</title>

<h1>Promiňte</h1>

<p>Web je momentálně mimo provoz z důvodu údržby. Zprovozněn by měl být během několika minut.</p>

<?php

exit;
