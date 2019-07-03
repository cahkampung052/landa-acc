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

$app->post('/acc/l_laba_rugi/laporan', function ($request, $response) {
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

        $arr_klasifikasi = [
            "PEMASUKAN" => "'Pendapatan', 'Pendapatan Usaha', 'Pendapatan Non Usaha'",
            "HPP" => "'Hpp'",
            "PENGELUARAN" => "'Biaya Operasional', 'Biaya Non Operasional'"
        ];
//        print_r($arr_klasifikasi);die();
//        $index = 0;

        foreach ($arr_klasifikasi as $index => $akun) {

            $arr[$index]['nama'] = $index;
            $arr[$index]['total'] = 0;

            $getakun = $sql->select("*")
                    ->from("acc_m_akun")
                    ->customWhere("tipe IN($akun)")
                    ->where("is_tipe", "=", 0)
                    ->where("is_deleted", "=", 0)
                    ->orderBy("kode")
                    ->findAll();


            foreach ($getakun as $key => $val) {

                $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                        ->from("acc_trans_detail")
                        ->where('acc_trans_detail.m_akun_id', '=', $val->id)
                        ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                        ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
                if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                    $sql->andWhere('acc_trans_detail.m_lokasi_id', '=', $params['m_lokasi_id']['id']);
                }
                $gettransdetail = $sql->find();
                if (intval($gettransdetail->debit) - intval($gettransdetail->kredit) > 0) {
                    $arr[$index]['detail'][$key]['kode'] = $val->kode;
                    $arr[$index]['detail'][$key]['nama'] = $val->nama;
                    $arr[$index]['detail'][$key]['nominal'] = intval($gettransdetail->debit) - intval($gettransdetail->kredit);
                    $arr[$index]['total'] += $arr[$index]['detail'][$key]['nominal'];
                }
            }
        }

//        print_r($arr);die();




        return successResponse($response, ["data" => $data, "detail" => $arr]);
    } else {
        return unprocessResponse($response, $validasi);
    }
});


$app->get('/acc/l_laba_rugi/exportExcel', function ($request, $response) {

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

    $arr_klasifikasi = [
        "PEMASUKAN" => "'Pendapatan', 'Pendapatan Usaha', 'Pendapatan Non Usaha'",
        "HPP" => "'Hpp'",
        "PENGELUARAN" => "'Biaya Operasional', 'Biaya Non Operasional'"
    ];
//        print_r($arr_klasifikasi);die();
//        $index = 0;

    foreach ($arr_klasifikasi as $index => $akun) {

        $arr[$index]['nama'] = $index;
        $arr[$index]['total'] = 0;

        $getakun = $sql->select("*")
                ->from("acc_m_akun")
                ->customWhere("tipe IN($akun)")
                ->where("is_tipe", "=", 0)
                ->where("is_deleted", "=", 0)
                ->orderBy("kode")
                ->findAll();


        foreach ($getakun as $key => $val) {

            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('acc_trans_detail.m_akun_id', '=', $val->id)
                    ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                    ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
            if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                $sql->andWhere('acc_trans_detail.m_lokasi_id', '=', $params['m_lokasi_id']['id']);
            }
            $gettransdetail = $sql->find();
            if (intval($gettransdetail->debit) - intval($gettransdetail->kredit) > 0) {
                $arr[$index]['detail'][$key]['kode'] = $val->kode;
                $arr[$index]['detail'][$key]['nama'] = $val->nama;
                $arr[$index]['detail'][$key]['nominal'] = intval($gettransdetail->debit) - intval($gettransdetail->kredit);
                $arr[$index]['total'] += $arr[$index]['detail'][$key]['nominal'];
            }
        }
    }


    $path = 'acc/landaacc/upload/format_laba_rugi.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);

    $objPHPExcel->getActiveSheet()->setCellValue('A' . 4, "Lokasi : " . $data['lokasi']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 5, "Periode : " . $data['tanggal']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 6, "Disiapkan Pada : " . $data['disiapkan']);

    $row = 9;
    foreach ($arr as $key => $val) {

        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, $key);
        if (isset($val['detail'])) {
            $row2 = $row + 1;
            foreach ($val['detail'] as $keys => $vals) {
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $row2, $vals['kode']);
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $row2, $vals['nama']);
                $objPHPExcel->getActiveSheet()->setCellValue('C' . $row2, $vals['nominal']);
                $row2++;
            }
            $row = $row2 + 2;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row2), "TOTAL " . $key);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . ($row2), $val['total']);
            if($key == "HPP"){
                $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row2+1), "LABA KOTOR(PEMASUKAN-HPP)");
                $objPHPExcel->getActiveSheet()->setCellValue('C' . ($row2+1), $arr['PEMASUKAN']['total']-$arr['HPP']['total']);
                $row = $row2 + 3;
            }
            
        }else{
            $row2 = $row + 1;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row2), "TOTAL " . $key);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . ($row2), $val['total']);
            $row = $row2 + 2;
            if($key == "HPP"){
                $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row2+1), "LABA KOTOR(TOTAL PEMASUKAN - TOTAL HPP)");
                $objPHPExcel->getActiveSheet()->setCellValue('C' . ($row2+1), $arr['PEMASUKAN']['total']-$arr['HPP']['total']);
                $row = $row2 + 3;
            }
            
        }

        

        
    }

    if($arr['PEMASUKAN']['total']-($arr['HPP']['total']+$arr['PENGELUARAN']['total']) >= 0){
        $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row+1), "LABA");
        $objPHPExcel->getActiveSheet()->setCellValue('C' . ($row+1), $arr['PEMASUKAN']['total']-($arr['HPP']['total']+$arr['PENGELUARAN']['total']));
    }else{
        $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row+1), "RUGI");
        $objPHPExcel->getActiveSheet()->setCellValue('C' . ($row+1),($arr['PEMASUKAN']['total']-($arr['HPP']['total']+$arr['PENGELUARAN']['total']))*1);
    }
    
    
    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=laporan_laba_rugi.xls");

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});



