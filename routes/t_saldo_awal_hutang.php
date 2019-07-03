<?php

date_default_timezone_set('Asia/Jakarta');

$app->get('/acc/t_saldo_awal_hutang/getHutangAwal', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);die();
    $db = $this->db;
    $getsupplier = $db->select("*")->from("acc_m_supplier")->findAll();

    foreach ($getsupplier as $key => $val) {
        $getsupplier[$key] = (array) $val;
        $models = $db->select("acc_saldo_hutang.*, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun, acc_m_supplier.kode as kodeSup, acc_m_supplier.nama as namaSup")
                ->from("acc_saldo_hutang")
                ->join("join", "acc_m_akun", "acc_m_akun.id = acc_saldo_hutang.m_akun_id")
                ->join("join", "acc_m_supplier", "acc_m_supplier.id = acc_saldo_hutang.m_supplier_id")
//                ->where("acc_saldo_hutang.tanggal", "=", $params['tanggal'])
                ->where("acc_saldo_hutang.m_lokasi_id", "=", $params['m_lokasi_id'])
                ->where("acc_saldo_hutang.m_supplier_id", "=", $val->id)
                ->find();

        if ($models) {
            $getsupplier[$key]['saldo_id'] = $models->id;
            $getsupplier[$key]['total'] = $models->total;
            $getsupplier[$key]['m_akun_id'] = ["id" => $models->m_akun_id, "kode" => $models->kodeAkun, "nama" => $models->namaAkun];
        } else {
            $getsupplier[$key]['total'] = 0;
//            $getsupplier[$key]['m_akun_id'] = [];
        }
    }

//    echo '<pre>', print_r($getsupplier), '</pre>';die();
//    echo '<pre>', print_r($models), '</pre>';die();

    return successResponse($response, [
        'detail' => $getsupplier
    ]);
});


$app->post('/acc/t_saldo_awal_hutang/saveHutang', function ($request, $response) {
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
                    $detail['m_supplier_id'] = $val['id'];
                    $detail['m_lokasi_id'] = $m_lokasi_id;
                    $detail['m_akun_id'] = $val['m_akun_id']['id'];
                    $detail['tanggal'] = $tanggal;
                    $detail['total'] = $val['total'];
                    
                    if(isset($val['saldo_id']) && !empty($val['saldo_id'])){
                        $insert = $db->update('acc_saldo_hutang', $detail, ["id" => $val['saldo_id']]);
                    }else{
                        $insert = $db->insert('acc_saldo_hutang', $detail);
                    }
                    

                    $detail2['m_supplier_id'] = $val['id'];
                    $detail2['m_lokasi_id'] = $m_lokasi_id;
                    $detail2['m_akun_id'] = $val['m_akun_id']['id'];
                    $detail2['tanggal'] = $tanggal;
                    $detail2['kredit'] = $val['total'];
                    $detail2['reff_type'] = 'Saldo Hutang';
                    $detail2['reff_id'] = $insert->id;
                    $detail2['keterangan'] = 'Saldo Hutang';
                    
                    
                    if(isset($val['saldo_id']) && !empty($val['saldo_id'])){
                        $insert = $db->update('acc_trans_detail', $detail2, ["reff_id" => $val['saldo_id'], "reff_type"=>"Saldo Hutang"]);
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
