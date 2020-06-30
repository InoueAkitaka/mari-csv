<?php
// テーブル定義
define('M_USER', 'm_line_user_data');
define('T_TIME', 't_line_time_card');

	if ( $_POST['mode'] === 'download' ) {
		//echo 'testtesttest';
		
		$startDate =  $_POST['start'];
		$endDate =  $_POST['end'];
		//メモリ上に領域確保
		$fp = fopen('php://temp/maxmemory:'.(5*1024*1024),'r+');

		$export_csv_title = ["日付", "出勤時間", "退勤時間"]; //ヘッダー項目

		foreach($export_csv_title as $key => $val){

			$export_header[] = $val;
		}

		$dbh = dbConnection::getConnection();
		$sql = 'select stamp_date, attend_time, leave_time from ' . T_TIME . ' where user_srg = ? and stamp_date >= ? and stamp_date <= ?';
		$sth = $dbh->prepare($sql);
		$sth->execute(array($userId, $startDate, $endDate));

		foreach($export_header as $data){
			fputcsv($fp, $data);
		}

		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			fputcsv($fp, $row);
		}

		header('Content-Type: text/csv');
		header("Content-Disposition: attachment; filename=hoge.csv");

		//ファイルポインタを先頭へ
		rewind($fp);
		//リソースを読み込み文字列取得
		$csv = stream_get_contents($fp);

		//CSVをエクセルで開くことを想定して文字コードをSJIS-winSJISへ
		//$csv = mb_convert_encoding($csv,'SJIS-win','utf8');

		print $csv;

		fclose($fp);
		exit();
	} else {
		$userId = $_POST['personPage'];
		$monthData = $_POST['month'];

		// 前月一日
		//$startDate = date('Y-m-01 00:00:00', strtotime(date('Y-m-1'). '-1 month' ) );

		// パラメータ月の一日
		$startDate = date('Y-m-01 00:00:00', strtotime(date($monthData .'/1')));

		// 前月末日
		//$endDate = date('Y-m-t 23:59:59', strtotime(date('Y-m-1'). '-1 month' ) );

		$endDate = date('Y-m-t 23:59:59', strtotime(date($monthData .'/1')));
		
		echo $startDate;
		echo $endDate;

		$dbh = dbConnection::getConnection();
		$sql = 'select * from ' . M_USER . ' where ? = pgp_sym_decrypt(user_secret_id, \'' . getenv('DB_ENCRYPT_PASS') . '\')';
		$sth = $dbh->prepare($sql);
		$sth->execute(array($userId));

		// データが存在しない場合はNULL
		if (!($row = $sth->fetch())) {
			echo 'データの取得に失敗しました' . $userId;
		}
		else {
			//確認用のためコメントアウト
			//echo json_decode($row['another_user_name']);
			
			$userName = json_decode($row['another_user_name']);
		}
	}

// linebotのDBに接続
// 環境変数(getenv)はherokuのappに記載する必要がある
class dbConnection {
	// インスタンス
	protected static $db;
	
	// コンストラクタ
	private function __construct() {
		try {
			// 環境変数からデータベースへの接続情報を取得
			$url = parse_url(getenv('DATABASE_URL'));
			
			// データソース
			$dsn = sprintf('pgsql:host=%s;dbname=%s', $url['host'], substr($url['path'], 1));
			
			// 接続を確立
			self::$db = new PDO($dsn, $url['user'], $url['pass']);
			
			// エラー時、例外をスロー
			self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch (PDOException $e){
			echo 'Connection Error: ' . $e->getMessage();
		}
	}
	
	// シングルトン
	// 存在しない場合のみインスタンス化
	public static function getConnection() {
		if (!self::$db) {
			new dbConnection();
		}
		
		return self::$db;
	}
}
?>

<html>
	<head>
		<meta charset=“UFT-8”>
		<style>
			#work-table table,#work-table td,#work-table th {
				border-collapse: collapse;
				border:1px solid #333;
			}
		</style>
	</head>
<body>
	<h2><?php echo $userName. "_勤務時間一覧" ?></h2>
<?php 
	$dbh = dbConnection::getConnection();
	$sql = 'select stamp_date, attend_edit_time, leave_edit_time from ' . T_TIME . ' where user_srg = ? and stamp_date >= ? and stamp_date <= ?';
	$sth = $dbh->prepare($sql);
	$sth->execute(array($userId, $startDate, $endDate));

	$arrData = "";

	// データが存在しない場合はNULL
	if (!($row = $sth->fetch())) {
		echo 'データの取得に失敗しました';
	}
	else {
		$arrData .= "<tr>";
		$arrData .= "<td>". $row['stamp_date']. "</td>";
		$arrData .= "<td>". $row['attend_edit_time']. "</td>";
		$arrData .= "<td>". $row['leave_edit_time']. "</td>";
		$arrData .= "</tr>";

		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$arrData .= "<tr>";
			$arrData .= "<td>". $row['stamp_date']. "</td>";
			$arrData .= "<td>". $row['attend_edit_time']. "</td>";
			$arrData .= "<td>". $row['leave_edit_time']. "</td>";
			$arrData .= "</tr>";
		}
	}
?>
	<form action="" method="post">
		<div id="work-table">
			<table width="100%">
				<tr>
					<td>出勤日</td>
					<td>開始時刻</td>
					<td>終了時刻</td>
				</tr>
				<?php echo $arrData; ?>
			</table>
		</div>
		<button type='submit' name='mode' value='download'><?php echo $userName. "_CSVダウンロード" ?></button>
		<input type='hidden' name='start' value='<?php $startDate; ?>'>
		<input type='hidden' name='end' value='<?php $endDate; ?>'>
	</form>
</body>
</html>









