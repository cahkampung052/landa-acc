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

$app->post('/acc/l_neraca_saldo/laporan', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);die();
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



        $arr = [];
        $data['total_debit'] = 0;
        $data['total_kredit'] = 0;

        $getakun = $sql->select("*")
                ->from("acc_m_akun")
                ->where("is_deleted", "=", 0)
                ->findAll();

        foreach ($getakun as $key => $val) {

            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('m_akun_id', '=', $val->id)
                    ->andWhere('date(tanggal)', '<', $tanggal_start);
            $getsaldoawal = $sql->find();
            $arr2 = [];

            $arr2['kode'] = $val->kode;
            $arr2['nama'] = $val->nama;

            $arr2['saldo_awal'] = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

            $gettransdetail = $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('m_akun_id', '=', $val->id)
                    ->andWhere('date(tanggal)', '>=', $tanggal_start)
                    ->andWhere('date(tanggal)', '<=', $tanggal_end);

            $detail = $sql->find();
            $arr2['debit'] = $detail->debit;
            $arr2['kredit'] = $detail->kredit;

            $arr2['saldo_akhir'] = $arr2['saldo_awal'] + $arr2['debit'] - $arr2['kredit'];
            if ($arr2['saldo_awal'] != 0 || $arr2['debit'] != 0 || $arr2['kredit'] != 0) {
                $arr[$key] = $arr2;
                $data['total_debit'] += $arr2['debit'];
                $data['total_kredit'] += $arr2['kredit'];
            }
        }


//        echo json_encode($arr);die();

        return successResponse($response, ["data" => $data, "detail" => $arr]);
    } else {
        return unprocessResponse($response, $validasi);
    }
});


$app->get('/acc/l_neraca_saldo/exportExcel', function ($request, $response) {

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



    $arr = [];

    $getakun = $sql->select("*")
            ->from("acc_m_akun")
            ->where("is_deleted", "=", 0)
            ->findAll();

    foreach ($getakun as $key => $val) {

        $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '<', $tanggal_start);
        $getsaldoawal = $sql->find();
        $arr2 = [];

        $arr2['kode'] = $val->kode;
        $arr2['nama'] = $val->nama;

        $arr2['saldo_awal'] = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

        $gettransdetail = $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '>=', $tanggal_start)
                ->andWhere('date(tanggal)', '<=', $tanggal_end);

        $detail = $sql->find();
        $arr2['debit'] = $detail->debit;
        $arr2['kredit'] = $detail->kredit;

        $arr2['saldo_akhir'] = $arr2['saldo_awal'] + $arr2['debit'] - $arr2['kredit'];
        if ($arr2['saldo_awal'] != 0 || $arr2['debit'] != 0 || $arr2['kredit'] != 0) {
            $arr[$key] = $arr2;
        }
    }
//    echo "<pre>", print_r($arr), "</pre>";die();
//        foreach ($arr as $key => $val) {
//            $arr[$key] = (array) $val;
//            echo "<pre>", print_r($arr[$key]['detail']), "</pre>";
//        }
//        die();
    $path = 'acc/landaacc/upload/format_neraca_saldo.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);

    $objPHPExcel->getActiveSheet()->setCellValue('A' . 4, "Periode : " . $data['tanggal']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 5, "Disiapkan Pada : " . $data['disiapkan']);

    $row = 8;
    foreach ($arr as $key => $val) {

        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, $val['kode']);
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $row, $val['nama']);
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $row, $val['saldo_awal']);
        $objPHPExcel->getActiveSheet()->setCellValue('D' . $row, $val['debit']);
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $row, $val['kredit']);
        $objPHPExcel->getActiveSheet()->setCellValue('F' . $row, $val['saldo_akhir']);

        $row += 1;
    }

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=laporan_neraca_saldo.xls");

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});



