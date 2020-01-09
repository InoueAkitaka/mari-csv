<?php

// テーブル定義
define('M_USER', 'm_line_user_data');
define('T_TIME', 't_line_time_card');

	if ( $_POST['mode'] === 'download' ) {
		//echo 'testtesttest';
		
		$userSrg =  $_POST['userData'];
		//メモリ上に領域確保
		$fp = fopen('php://temp/maxmemory:'.(5*1024*1024),'r+');

		$export_csv_title = ["日付", "出勤時間", "退勤時間"]; //ヘッダー項目

		foreach($export_csv_title as $key => $val){

			$export_header[] = $val;
		}

		$dbh = dbConnection::getConnection();
		$sql = 'select stamp_date, attend_time, leave_time from ' . T_TIME . ' where user_srg = ? and stamp_date >= ? and stamp_date <= ?';
		$sth = $dbh->prepare($sql);
		$sth->execute(array($userSrg, '2019/12/01', '2019/12/31'));

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
	}

	$userId = $_GET['page'];

	echo $userId;

	$dbh = dbConnection::getConnection();
	$sql = 'select * from ' . M_USER . ' where ? = pgp_sym_decrypt(user_secret_id, \'' . getenv('DB_ENCRYPT_PASS') . '\')';
	$sth = $dbh->prepare($sql);
	$sth->execute(array($userId));

	// データが存在しない場合はNULL
	if (!($row = $sth->fetch())) {
		echo 'データの取得に失敗しました' . $userId;
	}
	else {
		echo json_decode($row['user_srg']);
		
		$userSrg = json_decode($row['user_srg']);
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
<body>
	<form action="" method="post">
		<input type="submit" value="csvダウンロード"><br />
		<input type="hidden" name="mode" value="download">
		<input type="hidden" name="userData" value="<?php echo $userSrg; ?>">
	</form>
	<p>test</p>
</body>
</html>
