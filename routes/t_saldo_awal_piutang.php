<?php

date_default_timezone_set('Asia/Jakarta');

$app->post('/acc/t_saldo_awal_piutang/getPiutangAwal', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);die();
    $db = $this->db;
    $tanggal = $params['tanggal'];
    $getcus = $db->select("*")->from("acc_m_kontak")->where("is_deleted", "=", 0)->where("type", "=", "customer")->findAll();

    foreach ($getcus as $key => $val) {
        $getcus[$key] = (array) $val;
        $models = $db->select("acc_saldo_piutang.*, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun, acc_m_kontak.kode as kodeCus, acc_m_kontak.nama as namaCus")
                ->from("acc_saldo_piutang")
                ->join("join", "acc_m_akun", "acc_m_akun.id = acc_saldo_piutang.m_akun_id")
                ->join("join", "acc_m_kontak", "acc_m_kontak.id = acc_saldo_piutang.m_kontak_id")
//                ->where("acc_saldo_hutang.tanggal", "=", $params['tanggal'])
                ->where("acc_saldo_piutang.m_lokasi_id", "=", $params['m_lokasi_id']['id'])
                ->where("acc_saldo_piutang.m_kontak_id", "=", $val->id)
                ->find();

        if ($models) {
            $tanggal = $models->tanggal;
            $getcus[$key]['saldo_id'] = $models->id;
            $getcus[$key]['total'] = $models->total;
            $getcus[$key]['m_akun_id'] = ["id" => $models->m_akun_id, "kode" => $models->kodeAkun, "nama" => $models->namaAkun];
        } 
    }

//    echo '<pre>', print_r($getcus), '</pre>';die();
//    echo '<pre>', print_r($models), '</pre>';die();

    return successResponse($response, [
        'detail' => $getcus,
        'tanggal' => $tanggal
    ]);
});


$app->post('/acc/t_saldo_awal_piutang/savePiutang', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);
//    die();
    if (isset($params['form']['tanggal']) && !empty($params['form']['tanggal'])) {
        $tanggal = new DateTime($params['form']['tanggal']);
        $tanggal->setTimezone(new DateTimeZone('Asia/Jakarta'));
        $tanggal = $tanggal->format("Y-m-d");
        $m_lokasi_id = $params['form']['m_lokasi_id']['id'];

        if (!empty($params['detail'])) {
            $db = $this->db;
            
            foreach ($params['detail'] as $val) {
                if (isset($val['total']) && !empty($val['total']) && isset($val['m_akun_id']) && !empty($val['m_akun_id'])) {
                    $detail['m_kontak_id'] = $val['id'];
                    $detail['m_lokasi_id'] = $m_lokasi_id;
                    $detail['m_akun_id'] = $val['m_akun_id']['id'];
                    $detail['tanggal'] = $tanggal;
                    $detail['total'] = $val['total'];
                    
                    if(isset($val['saldo_id']) && !empty($val['saldo_id'])){
                        $insert = $db->update('acc_saldo_piutang', $detail, ["id" => $val['saldo_id']]);
                    }else{
                        $insert = $db->insert('acc_saldo_piutang', $detail);
                    }
                    
                    $detail2['m_kontak_id'] = $val['id'];
                    $detail2['m_lokasi_id'] = $m_lokasi_id;
                    $detail2['m_akun_id'] = $val['m_akun_id']['id'];
                    $detail2['tanggal'] = $tanggal;
                    $detail2['debit'] = $val['total'];
                    $detail2['reff_type'] = 'acc_saldo_piutang';
                    $detail2['reff_id'] = $insert->id;
                    $detail2['keterangan'] = 'Saldo Piutang';
                    
                    /*
                     * akun pengimbang
                     */
                    $getakun = $db->select("*")->from("acc_m_akun_peta")->where("type", "=", "Pengimbang Neraca")->find();
                    $detail_['m_kontak_id'] = $val['id'];
                    $detail_['m_lokasi_id'] = $m_lokasi_id;
                    $detail_['m_akun_id'] = $getakun->m_akun_id;
                    $detail_['tanggal'] = $tanggal;
                    $detail_['kredit'] = $val['total'];
                    $detail_['reff_type'] = 'acc_saldo_piutang';
                    $detail_['reff_id'] = $insert->id;
                    $detail_['keterangan'] = 'Saldo Piutang';
                    
                    if(isset($val['saldo_id']) && !empty($val['saldo_id'])){
                        $insert = $db->update('acc_trans_detail', $detail2, ["reff_id" => $val['saldo_id'], "reff_type"=>"acc_saldo_piutang"]);
                        $insert = $db->update('acc_trans_detail', $detail_, ["reff_id" => $val['saldo_id'], "reff_type"=>"acc_saldo_piutang"]);
                    }else{
                        $insert2 = $db->insert('acc_trans_detail', $detail2);
                        $insert2 = $db->insert('acc_trans_detail', $detail_);
                    }
                }
            }

            return successResponse($response, []);
        }

        return unprocessResponse($response, ['Silahkan buat akun terlebih dahulu']);
    }

    return unprocessResponse($response, ['Tanggal tidak boleh kosong']);
});
