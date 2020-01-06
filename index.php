<?php
// ダウンロードするサーバのファイルパス
	$filepath = 'sample.csv';

	// HTTPヘッダを設定
	header('Content-Type: application/octet-stream');
	header('Content-Length: '.filesize($filepath));
	header('Content-Disposition: attachment; filename=download.csv');

	// ファイル出力
	readfile($filepath);

	echo $_GET['page'];
?>
