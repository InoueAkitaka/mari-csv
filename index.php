<?php

// テーブル定義
define('M_USER', 'm_line_user_data');
define('T_TIME', 't_line_time_card');

// 時間を切り上げる
//$nowEditTime = ceilPerTime(strtotime(date("H:i:s")), 15);

//echo ceilPerTime(15);

	if ( $_POST['mode'] === 'download' ) {
		//echo 'testtesttest';
		
		$userSrg =  $_POST['userData'];
		//メモリ上に領域確保
		$fp = fopen('php://temp/maxmemory:'.(5*1024*1024),'r+');

		$export_csv_title = ["日付", "出勤時間", "退勤時間"]; //ヘッダー項目

		foreach($export_csv_title as $key => $val){

			$export_header[] = $val;
		}

		// 前月一日
		$startDate = date('Y-m-01 00:00:00', strtotime(date('Y-m-1'). '-1 month' ) );

		// 前月末日
		$endDate = date('Y-m-t 23:59:59', strtotime(date('Y-m-1'). '-1 month' ) );

		$dbh = dbConnection::getConnection();
		$sql = 'select stamp_date, attend_time, leave_time from ' . T_TIME . ' where user_srg = ? and stamp_date >= ? and stamp_date <= ?';
		$sth = $dbh->prepare($sql);
		$sth->execute(array($userSrg, $startDate, $endDate));

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

	//確認用のためコメントアウト
	//echo $userId;

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
		//echo json_decode($row['user_srg']);
		
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

/**
 * 時間(hhmm)を指定した分単位で切り上げる
 * 
 * @param $per 切り上げる単位(分) 5分なら5
 * @return false or 切り上げられた DateTime オブジェクト(->fomat で自由にフォーマットして使用する)
 */
function ceilPerTime($per){

        $deteObj = new DateTime();
        // 指定された単位で切り上げる
        // フォーマット文字 i だと、 例えば1分が 2桁の 01 となる(1桁は無い）ので、整数に変換してから切り上げる
        $ceil_num = ceil(sprintf('%d', $deteObj->format('i'))/$per) *$per;

        // 切り上げた「分」が60になったら「時間」を1つ繰り上げる
        // 60分 -> 00分に直す
        $hour = $deteObj->format('H');

        if( $ceil_num == 60 ) {
            $hour = $deteObj->modify('+1 hour')->format('H');
            $ceil_num = '00';
        }
        $have = $hour.sprintf( '%02d', $ceil_num );

        return new DateTime($have);
}

/**
 * 時間(hhmm)を指定した分単位で切り捨てる
 * 
 * @param $time 時間と分の文字列(1130, 11:30など)
 * @param $per 切り捨てる単位(分) 5分なら5
 * @return false or 切り捨てられた DateTime オブジェクト(->fomat で自由にフォーマットして使用する)
 */
function floorPerTime($time, $per){

    // 値がない時、単位が0の時は false を返して終了する
    if( !isset($time) || !is_numeric($per) || ($per == 0 )) {
        return false;
    }else{
        $deteObj = new DateTime($time);

        // 指定された単位で切り捨てる
        // フォーマット文字 i だと、 例えば1分が 2桁の 01 となる(1桁は無い）ので、整数に変換してから切り捨てる
        $ceil_num = floor(sprintf('%d', $deteObj->format('i'))/$per) *$per;

        $hour = $deteObj->format('H');

        $have = $hour.sprintf( '%02d', $ceil_num );

        return new DateTime($have);
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
<?php 
	$dbh = dbConnection::getConnection();
	$sql = 'select to_char(stamp_date, \'yyyy/mm\') as work_month from ' . T_TIME . ' group by to_char(stamp_date, \'yyyy/mm\') order by work_month desc';
	$sth = $dbh->prepare($sql);
	$sth->execute();

	$arrMonth = "";

	// データが存在しない場合はNULL
	if (!($row = $sth->fetch())) {
		echo 'データの取得に失敗しました';
	}
	else {
		$arrMonth .= "<option value='". $row['work_month'];
		$arrMonth .= "'>". $row['work_month']. "</option>";

		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$arrMonth .= "<option value='". $row['work_month'];
			$arrMonth .= "'>". $row['work_month']. "</option>";
		}
	}

	$dbh = dbConnection::getConnection();
	$sql = 'select B.user_srg , B.another_user_name , sum(leave_edit_time) - sum(attend_edit_time) as work_time , count(stamp_date) as work_day from t_line_time_card A inner join m_line_user_data B on A.user_srg = B.user_srg where stamp_date >= \'2018/07/01\' and stamp_date <= \'2018/07/31\' group by B.user_srg, B.another_user_name order by work_time desc';
	$sth = $dbh->prepare($sql);
	$sth->execute();

	$arrData = "";

	// データが存在しない場合はNULL
	if (!($row = $sth->fetch())) {
		echo 'データの取得に失敗しました';
	}
	else {
		$arrData .= "<tr>";
		$arrData .= "<td>". $row['another_user_name']. "</td>";
		$arrData .= "<td>". $row['work_time']. "</td>";
		$arrData .= "<td>". $row['work_day']. "</td>";
		$arrData .= "<td><input id='test' type='button' value='test' onclick='window.open(url,'_blank')'></td>";
		$arrData .= "</tr>";

		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$arrData .= "<tr>";
			$arrData .= "<td>". $row['another_user_name']. "</td>";
			$arrData .= "<td>". $row['work_time']. "</td>";
			$arrData .= "<td>". $row['work_day']. "</td>";
			$arrData .= "<td><input id='test' type='button' value='test' onclick='window.open(url,'_blank')'></td>";
			$arrData .= "</tr>";
		}
	}
?>
	<form action="" method="post">
		<select name="month">
			<?php echo $arrMonth; ?>
		</select>
		
		<div id="work-table">
			<table width="100%">
				<tr>
					<td>氏名</td>
					<td>月合計勤務時間</td>
					<td>出勤日数</td>
					<td>個人別勤務時間ダウンロード</td>
				</tr>
				<?php echo $arrData; ?>
			</table>
		</div>
		<input type="submit" value="csvダウンロード"><br />
		<input type="hidden" name="mode" value="download">
		<input type="hidden" name="userData" value="<?php echo $userSrg; ?>">
	</form>
</body>
</html>

















