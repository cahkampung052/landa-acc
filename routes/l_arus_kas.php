<?php

function validasi($data, $custom = array()) {
    $validasi = array(
//        'parent_id' => 'required',
//        'kode'      => 'required',
//        'nama'      => 'required',
            // 'tipe' => 'required',
    );
//    GUMP::set_field_name("parent_id", "Akun");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->post('/acc/l_arus_kas/laporan', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);
//    die();
    $sql = $this->db;
    $validasi = validasi($params);
    if ($validasi === true) {

        //tanggal awal
        $tanggal_awal = new DateTime($params['tanggal']['startDate']);
        $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));

        //tanggal akhir
        $tanggal_akhir = new DateTime($params['tanggal']['endDate']);
        $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));

        $tanggal_start = $tanggal_awal->format("Y-m-d");
        $tanggal_end = $tanggal_akhir->format("Y-m-d");



        $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' Sampai ' . date("d-m-Y", strtotime($tanggal_end));
        $data['disiapkan'] = date("d-m-Y, H:i");
        $data['lokasi'] = "Semua";
        if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
            $data['lokasi'] = $params['m_lokasi_id']['nama'];
        }

        $data['saldo_awal'] = 0;
        $data['total_saldo'] = 0;

        $arr = [];

        $arr_klasifikasi = ["Aktivitas Operasi", "Investasi", "Pendanaan"];

        $index = 0;

        foreach ($arr_klasifikasi as $index => $akun) {

            $arr[$index]['nama'] = $akun;
            $arr[$index]['saldo'] = 0;

            $getakun = $sql->select("*")
                    ->from("acc_m_akun")
                    ->where("is_tipe", "=", 0)
                    ->where("is_deleted", "=", 0)
                    ->where("tipe_arus", "=", $akun)
                    ->orderBy("kode")
                    ->findAll();
            foreach ($getakun as $key => $val) {

                $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                        ->from("acc_trans_detail")
                        ->where('m_akun_id', '=', $val->id)
                        ->andWhere('date(tanggal)', '<', $tanggal_start);
                if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                    $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
                }
                $getsaldoawal = $sql->find();
                $data['saldo_awal'] += intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

                $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                        ->from("acc_trans_detail")
                        ->where('acc_trans_detail.m_akun_id', '=', $val->id)
                        ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                        ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
                if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                    $sql->andWhere('acc_trans_detail.m_lokasi_id', '=', $params['m_lokasi_id']['id']);
                }
                $gettransdetail = $sql->find();
                $arr[$index]['saldo'] += intval($gettransdetail->debit) - intval($gettransdetail->kredit);
                $data['total_saldo'] += $arr[$index]['saldo'];
            }
        }

        $data['kas'] = $data['total_saldo'] - $data['saldo_awal'];
//        print_r($data);die();




        return successResponse($response, ["data" => $data, "detail" => $arr]);
    } else {
        return unprocessResponse($response, $validasi);
    }
});


$app->get('/acc/l_arus_kas/exportExcel', function ($request, $response) {

    $params = $request->getParams();
//    echo json_encode($params) ;die();
//    $tahun = $params['tahun'];
//    echo $tahun;die();
    $sql = $this->db;

    //tanggal awal
    $tanggal_awal = new DateTime($params['tanggal']['startDate']);
    $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));

    //tanggal akhir
    $tanggal_akhir = new DateTime($params['tanggal']['endDate']);
    $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));

    $tanggal_start = $tanggal_awal->format("Y-m-d");
    $tanggal_end = $tanggal_akhir->format("Y-m-d");



    $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' Sampai ' . date("d-m-Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = "Semua";
    if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
        $data['lokasi'] = $params['m_lokasi_id']['nama'];
    }

    $data['saldo_awal'] = 0;
    $data['total_saldo'] = 0;

    $arr = [];

    $arr_klasifikasi = ["Aktivitas Operasi", "Investasi", "Pendanaan"];

    $index = 0;

    foreach ($arr_klasifikasi as $index => $akun) {

        $arr[$index]['nama'] = $akun;
        $arr[$index]['saldo'] = 0;

        $getakun = $sql->select("*")
                ->from("acc_m_akun")
                ->where("is_tipe", "=", 0)
                ->where("is_deleted", "=", 0)
                ->where("tipe_arus", "=", $akun)
                ->orderBy("kode")
                ->findAll();
        foreach ($getakun as $key => $val) {

            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('m_akun_id', '=', $val->id)
                    ->andWhere('date(tanggal)', '<', $tanggal_start);
            if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
            }
            $getsaldoawal = $sql->find();
            $data['saldo_awal'] += intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('acc_trans_detail.m_akun_id', '=', $val->id)
                    ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                    ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
            if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                $sql->andWhere('acc_trans_detail.m_lokasi_id', '=', $params['m_lokasi_id']['id']);
            }
            $gettransdetail = $sql->find();
            $arr[$index]['saldo'] += intval($gettransdetail->debit) - intval($gettransdetail->kredit);
            $data['total_saldo'] += $arr[$index]['saldo'];
        }
    }

    $data['kas'] = $data['total_saldo'] - $data['saldo_awal'];

//    echo '<pre>',print_r($data), '</pre>';
//    echo '<pre>',print_r($arr), '</pre>';die();
    
    $path = 'acc/landaacc/upload/format_arus_kas.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);

    $objPHPExcel->getActiveSheet()->setCellValue('A' . 3, "Lokasi : " . $data['lokasi']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 4, "Periode : " . $data['tanggal']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 5, "Disiapkan Pada : " . $data['disiapkan']);

    $row = 9;
    foreach ($arr as $key => $val) {

//        $row++;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, $val['nama']);
        $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row+1), $val['nama']);
        $objPHPExcel->getActiveSheet()->setCellValue('B' . ($row+1), $val['saldo']);
        $row = $row+2;
    }
    
    $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, "Total Arus Kas");
    $objPHPExcel->getActiveSheet()->setCellValue('B' . $row, $data['total_saldo']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row+1), "Saldo Awal");
    $objPHPExcel->getActiveSheet()->setCellValue('B' . ($row+1), $data['saldo_awal']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row+2), "Saldo Akhir");
    $objPHPExcel->getActiveSheet()->setCellValue('B' . ($row+2), 0);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row+3), "Penurunan / Kenaikan Kas");
    $objPHPExcel->getActiveSheet()->setCellValue('B' . ($row+3), $data['kas']);

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=laporan_arus_kas.xls");

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});



