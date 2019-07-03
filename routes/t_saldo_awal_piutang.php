<?php

date_default_timezone_set('Asia/Jakarta');

$app->get('/acc/t_saldo_awal_piutang/getPiutangAwal', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);die();
    $db = $this->db;
    $getcus = $db->select("*")->from("acc_m_customer")->findAll();

    foreach ($getcus as $key => $val) {
        $getcus[$key] = (array) $val;
        $models = $db->select("acc_saldo_piutang.*, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun, acc_m_customer.kode as kodeCus, acc_m_customer.nama as namaCus")
                ->from("acc_saldo_piutang")
                ->join("join", "acc_m_akun", "acc_m_akun.id = acc_saldo_piutang.m_akun_id")
                ->join("join", "acc_m_customer", "acc_m_customer.id = acc_saldo_piutang.m_customer_id")
//                ->where("acc_saldo_hutang.tanggal", "=", $params['tanggal'])
                ->where("acc_saldo_piutang.m_lokasi_id", "=", $params['m_lokasi_id'])
                ->where("acc_saldo_piutang.m_customer_id", "=", $val->id)
                ->find();

        if ($models) {
            $getcus[$key]['saldo_id'] = $models->id;
            $getcus[$key]['total'] = $models->total;
            $getcus[$key]['m_akun_id'] = ["id" => $models->m_akun_id, "kode" => $models->kodeAkun, "nama" => $models->namaAkun];
        } else {
            $getcus[$key]['total'] = 0;
//            $getsupplier[$key]['m_akun_id'] = [];
        }
    }

//    echo '<pre>', print_r($getcus), '</pre>';die();
//    echo '<pre>', print_r($models), '</pre>';die();

    return successResponse($response, [
        'detail' => $getcus
    ]);
});


$app->post('/acc/t_saldo_awal_piutang/savePiutang', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);
//    die();
    if (isset($params['form']['tanggal']) && !empty($params['form']['tanggal'])) {
        $tanggal = date("Y-m-d", strtotime($params['form']['tanggal']));
        $m_lokasi_id = $params['form']['m_lokasi_id'];

        if (!empty($params['detail'])) {
            $db = $this->db;
            
            foreach ($params['detail'] as $val) {
                if (isset($val['total']) && !empty($val['total']) && isset($val['m_akun_id']) && !empty($val['m_akun_id'])) {
                    $detail['m_customer_id'] = $val['id'];
                    $detail['m_lokasi_id'] = $m_lokasi_id;
                    $detail['m_akun_id'] = $val['m_akun_id']['id'];
                    $detail['tanggal'] = $tanggal;
                    $detail['total'] = $val['total'];
                    
                    if(isset($val['saldo_id']) && !empty($val['saldo_id'])){
                        $insert = $db->update('acc_saldo_piutang', $detail, ["id" => $val['saldo_id']]);
                    }else{
                        $insert = $db->insert('acc_saldo_piutang', $detail);
                    }
                    

                    $detail2['m_customer_id'] = $val['id'];
                    $detail2['m_lokasi_id'] = $m_lokasi_id;
                    $detail2['m_akun_id'] = $val['m_akun_id']['id'];
                    $detail2['tanggal'] = $tanggal;
                    $detail2['kredit'] = $val['total'];
                    $detail2['reff_type'] = 'Saldo Piutang';
                    $detail2['reff_id'] = $insert->id;
                    $detail2['keterangan'] = 'Saldo Piutang';
                    
                    
                    if(isset($val['saldo_id']) && !empty($val['saldo_id'])){
                        $insert = $db->update('acc_trans_detail', $detail2, ["reff_id" => $val['saldo_id'], "reff_type"=>"Saldo Piutang"]);
                    }else{
                        $insert2 = $db->insert('acc_trans_detail', $detail2);
                    }
                }
            }

            return successResponse($response, []);
        }

        return unprocessResponse($response, ['Silahkan buat akun terlebih dahulu']);
    }

    return unprocessResponse($response, ['Tanggal tidak boleh kosong']);
});
