<?php

// テーブル定義
define('M_USER', 'm_line_user_data');
define('T_TIME', 't_line_time_card');

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
		
		
	}

	if ( $_POST['mode'] === 'download' ) {
			//仮のデータ
			$data[0]['fruit'] = "オレンジ";
			$data[0]['price'] = "100円";
			$data[1]['fruit'] = "グレープ";
			$data[1]['price'] = "200円";
			$data[2]['fruit'] = "桃";
			$data[2]['price'] = "300円";
			  
			//配列にデータが入っている場合は1行の文字列にしてカンマ区切りのデータにしましょう
			//末尾は改行コードで。''じゃなく、""でくくりましょう。
			for ( $i = 0 ; $i < count ( $data ) ; $i ++ ) {
				$csv_data.= $data[$i]['fruit'].','.$data[$i]['price']."\n";
			}
			//出力ファイル名の作成
			$csv_file = "csv_". date ( "Ymd" ) .'.csv';
		  
			//文字化けを防ぐ
			$csv_data = mb_convert_encoding ( $csv_data , "sjis-win" , 'utf-8' );
			  
			//MIMEタイプの設定
			header("Content-Type: application/octet-stream");
			//名前を付けて保存のダイアログボックスのファイル名の初期値
			header("Content-Disposition: attachment; filename={$csv_file}");
		  
			// データの出力
			echo($csv_data);
			exit();
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
	</form>
	<p>test</p>
</body>
</html>
