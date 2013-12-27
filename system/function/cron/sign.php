<?php
if(!defined('IN_KKFRAME')) exit();
$date = date('Ymd', TIMESTAMP);
$count = DB::result_first("SELECT COUNT(*) FROM `sign_log` WHERE status IN (0, 1) AND date='{$date}'");
if($count){
	$num = 0;
	$first = true;
	while($num++ < 30){
		$offset = rand(1, $count) - 1;
		$res = DB::fetch_first("SELECT tid, status FROM `sign_log` WHERE status IN (0, 1) AND date='{$date}' LIMIT {$offset},1");
		$tid = $res['tid'];
		if(!$tid) break;
		if($res['status'] == 2 || $res['status'] == -2) continue;
		$tieba = DB::fetch_first("SELECT * FROM my_tieba WHERE tid='{$tid}'");
		if($tieba['skiped'] || !$tieba){
			DB::query("UPDATE sign_log set status='-2' WHERE tid='{$tieba[tid]}' AND date='{$date}'");
			continue;
		}
		$uid = $tieba['uid'];
		$setting = get_setting($uid);
		list($status, $result, $exp) = client_sign($uid, $tieba);
		if($status == 2){
			if($exp){
				DB::query("UPDATE sign_log SET status='2', exp='{$exp}' WHERE tid='{$tieba[tid]}' AND date='{$date}'");
				$num++;
				$time = 2;
			}else{
				DB::query("UPDATE sign_log SET status='2' WHERE tid='{$tieba[tid]}' AND date='{$date}' AND status<2");
				$time = 0;
			}
		}else{
			$retry = DB::result_first("SELECT retry FROM sign_log WHERE tid='{$tieba[tid]}' AND date='{$date}' AND status<2");
			if($retry >= 100){
				DB::query("UPDATE sign_log SET status='-1' WHERE tid='{$tieba[tid]}' AND date='{$date}' AND status<2");
			}elseif($status == 1){
				DB::query("UPDATE sign_log SET status='1', retry=retry+1 WHERE tid='{$tieba[tid]}' AND date='{$date}' AND status<2");
			}else{
				DB::query("UPDATE sign_log SET status='1', retry=retry+15 WHERE tid='{$tieba[tid]}' AND date='{$date}' AND status<2");
			}
			$time = 1;
		}
		if(!defined('SIGN_LOOP')) break;
		if($time) sleep($time);
		if($time && $count > 1) $count--;
	}
}else{
	define('CRON_FINISHED', true);
}
