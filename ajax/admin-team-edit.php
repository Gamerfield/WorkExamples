<?
	$LogInRequired=true;
	$AjaxPost=true;
	require_once('../../../lib/php/KyleLib/tap-KyleLib.php');
	require_once('../../../lib/php/KyleLib/Corp.php');
	
	if(isset($_POST['Method'])) {
		// echo 'Received: '.$_POST['Method'];
		$CorpO = new Corp();
		$CorpO = $CorpO->GetCorp($_POST['CorpID']);
		$results = $CorpO->{$_POST['Method']}($_POST['Data']);
		echo json_encode($results);
	}
	
	

?>
