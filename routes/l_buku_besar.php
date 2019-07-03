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

$app->get('/acc/l_buku_besar/listakun', function ($request, $response) {
    $sql = $this->db;

    $data = $sql->findAll('select * from acc_m_akun where is_deleted = 0 order by kode');
    foreach ($data as $key => $val) {
        $data[$key] = (array) $val;
        $spasi = ($val->level == 1) ? '' : str_repeat("···", $val->level - 1);
        $data[$key]['nama_lengkap'] = $spasi . $val->kode . ' - ' . $val->nama;
    }
    return successResponse($response, $data);
});

$app->post('/acc/l_buku_besar/laporan', function ($request, $response) {
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
        $data['lokasi'] = "Semua";
        if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
            $data['lokasi'] = $params['m_lokasi_id']['nama'];
        }



        $arr = [];

        if ($params['m_akun_id']['is_tipe'] == 1) {
            $getchild = $sql->select("*")
                    ->from("acc_m_akun")
                    ->where("parent_id", "=", $params['m_akun_id']['id'])
                    ->where("is_deleted", "=", 0)
                    ->findAll();
//            print_r($getchild);die();
            foreach ($getchild as $key => $val) {

                $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                        ->from("acc_trans_detail")
                        ->where('m_akun_id', '=', $params['m_akun_id']['id'])
                        ->andWhere('date(tanggal)', '<', $tanggal_start);
                if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                    $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
                }
                $getsaldoawal = $sql->find();
                $arr[$key]['saldo_awal'] = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);
                $arr[$key]['debit_awal'] = intval($getsaldoawal->debit);
                $arr[$key]['kredit_awal'] = intval($getsaldoawal->kredit);

                $arr[$key]['akun'] = $val->kode . ' - ' . $val->nama;
                $gettransdetail = $sql->select("*")
                        ->from("acc_trans_detail")
                        ->where('m_akun_id', '=', $val->id)
                        ->andWhere('date(tanggal)', '>=', $tanggal_start)
                        ->andWhere('date(tanggal)', '<=', $tanggal_end)
                        ->orderBy('tanggal');
                if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                    $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
                }

                $detail = $sql->findAll();
                $saldo_sekarang = $arr[$key]['saldo_awal'];
                $total_debit = $arr[$key]['debit_awal'];
                $total_kredit = $arr[$key]['kredit_awal'];
                foreach ($detail as $key2 => $val2) {
                    $arr[$key]['detail'][$key2]['tanggal'] = $val2->tanggal;
                    $arr[$key]['detail'][$key2]['kode'] = $val2->kode;
                    $arr[$key]['detail'][$key2]['keterangan'] = $val2->keterangan;
                    $arr[$key]['detail'][$key2]['debit'] = $val2->debit;
                    $arr[$key]['detail'][$key2]['kredit'] = $val2->kredit;
                    $arr[$key]['detail'][$key2]['saldo'] = intval($val2->debit) - intval($val2->kredit);
                    $saldo_sekarang += $arr[$key]['detail'][$key2]['saldo'];
                    $arr[$key]['detail'][$key2]['saldo_sekarang'] = $saldo_sekarang;
                    $total_debit += intval($val2->debit);
                    $total_kredit += intval($val2->kredit);
                }
                $arr[$key]['total_debit'] = $total_debit;
                $arr[$key]['total_kredit'] = $total_kredit;
                $arr[$key]['total_saldo'] = $total_debit - $total_kredit;
            }
        } else {
            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('m_akun_id', '=', $params['m_akun_id']['id'])
                    ->andWhere('date(tanggal)', '<', $tanggal_start);
            if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
            }
            $getsaldoawal = $sql->find();
            $arr[0]['saldo_awal'] = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);
            $arr[0]['debit_awal'] = intval($getsaldoawal->debit);
            $arr[0]['kredit_awal'] = intval($getsaldoawal->kredit);

            $arr[0]['akun'] = $params['m_akun_id']['kode'] . ' - ' . $params['m_akun_id']['nama'];
            $gettransdetail = $sql->select("*")
                    ->from("acc_trans_detail")
                    ->where('m_akun_id', '=', $params['m_akun_id']['id'])
                    ->andWhere('date(tanggal)', '>=', $tanggal_start)
                    ->andWhere('date(tanggal)', '<=', $tanggal_end)
                    ->orderBy('tanggal');
            if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
            }

            $detail = $sql->findAll();

            $saldo_sekarang = $arr[0]['saldo_awal'];
            $total_debit = $arr[0]['debit_awal'];
            $total_kredit = $arr[0]['kredit_awal'];
            foreach ($detail as $key2 => $val2) {
                $arr[0]['detail'][$key2]['tanggal'] = $val2->tanggal;
                $arr[0]['detail'][$key2]['kode'] = $val2->kode;
                $arr[0]['detail'][$key2]['keterangan'] = $val2->keterangan;
                $arr[0]['detail'][$key2]['debit'] = $val2->debit;
                $arr[0]['detail'][$key2]['kredit'] = $val2->kredit;
                $arr[0]['detail'][$key2]['saldo'] = intval($val2->debit) - intval($val2->kredit);
                $saldo_sekarang += $arr[0]['detail'][$key2]['saldo'];
                $arr[0]['detail'][$key2]['saldo_sekarang'] = $saldo_sekarang;
                $total_debit += intval($val2->debit);
                $total_kredit += intval($val2->kredit);
            }

            $arr[0]['total_debit'] = $total_debit;
            $arr[0]['total_kredit'] = $total_kredit;
            $arr[0]['total_saldo'] = $total_debit - $total_kredit;
        }

//        echo json_encode($arr);die();

        return successResponse($response, ["data" => $data, "detail" => $arr]);
    } else {
        return unprocessResponse($response, $validasi);
    }
});


$app->get('/acc/l_buku_besar/exportExcel', function ($request, $response) {

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



    $arr = [];

    if ($params['m_akun_id']['is_tipe'] == 1) {
        $getchild = $sql->select("*")
                ->from("acc_m_akun")
                ->where("parent_id", "=", $params['m_akun_id']['id'])
                ->where("is_deleted", "=", 0)
                ->findAll();
//            print_r($getchild);die();
        foreach ($getchild as $key => $val) {

            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('m_akun_id', '=', $params['m_akun_id']['id'])
                    ->andWhere('date(tanggal)', '<', $tanggal_start);
            if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
            }
            $getsaldoawal = $sql->find();
            $arr[$key]['saldo_awal'] = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);
            $arr[$key]['debit_awal'] = intval($getsaldoawal->debit);
            $arr[$key]['kredit_awal'] = intval($getsaldoawal->kredit);

            $arr[$key]['akun'] = $val->kode . ' - ' . $val->nama;
            $gettransdetail = $sql->select("*")
                    ->from("acc_trans_detail")
                    ->where('m_akun_id', '=', $val->id)
                    ->andWhere('date(tanggal)', '>=', $tanggal_start)
                    ->andWhere('date(tanggal)', '<=', $tanggal_end)
                    ->orderBy('tanggal');
            if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
            }

            $detail = $sql->findAll();
            $saldo_sekarang = $arr[$key]['saldo_awal'];
            $total_debit = $arr[$key]['debit_awal'];
            $total_kredit = $arr[$key]['kredit_awal'];
            foreach ($detail as $key2 => $val2) {
                $arr[$key]['detail'][$key2]['tanggal'] = $val2->tanggal;
                $arr[$key]['detail'][$key2]['kode'] = $val2->kode;
                $arr[$key]['detail'][$key2]['keterangan'] = $val2->keterangan;
                $arr[$key]['detail'][$key2]['debit'] = $val2->debit;
                $arr[$key]['detail'][$key2]['kredit'] = $val2->kredit;
                $arr[$key]['detail'][$key2]['saldo'] = intval($val2->debit) - intval($val2->kredit);
                $saldo_sekarang += $arr[$key]['detail'][$key2]['saldo'];
                $arr[$key]['detail'][$key2]['saldo_sekarang'] = $saldo_sekarang;
                $total_debit += intval($val2->debit);
                $total_kredit += intval($val2->kredit);
            }
            $arr[$key]['total_debit'] = $total_debit;
            $arr[$key]['total_kredit'] = $total_kredit;
            $arr[$key]['total_saldo'] = $total_debit - $total_kredit;
        }
    } else {
        $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $params['m_akun_id']['id'])
                ->andWhere('date(tanggal)', '<', $tanggal_start);
        if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
            $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
        }
        $getsaldoawal = $sql->find();
        $arr[0]['saldo_awal'] = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);
        $arr[0]['debit_awal'] = intval($getsaldoawal->debit);
        $arr[0]['kredit_awal'] = intval($getsaldoawal->kredit);

        $arr[0]['akun'] = $params['m_akun_id']['kode'] . ' - ' . $params['m_akun_id']['nama'];
        $gettransdetail = $sql->select("*")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $params['m_akun_id']['id'])
                ->andWhere('date(tanggal)', '>=', $tanggal_start)
                ->andWhere('date(tanggal)', '<=', $tanggal_end)
                ->orderBy('tanggal');
        if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
            $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
        }

        $detail = $sql->findAll();

        $saldo_sekarang = $arr[0]['saldo_awal'];
        $total_debit = $arr[0]['debit_awal'];
        $total_kredit = $arr[0]['kredit_awal'];
        foreach ($detail as $key2 => $val2) {
            $arr[0]['detail'][$key2]['tanggal'] = $val2->tanggal;
            $arr[0]['detail'][$key2]['kode'] = $val2->kode;
            $arr[0]['detail'][$key2]['keterangan'] = $val2->keterangan;
            $arr[0]['detail'][$key2]['debit'] = $val2->debit;
            $arr[0]['detail'][$key2]['kredit'] = $val2->kredit;
            $arr[0]['detail'][$key2]['saldo'] = intval($val2->debit) - intval($val2->kredit);
            $saldo_sekarang += $arr[0]['detail'][$key2]['saldo'];
            $arr[0]['detail'][$key2]['saldo_sekarang'] = $saldo_sekarang;
            $total_debit += intval($val2->debit);
            $total_kredit += intval($val2->kredit);
        }

        $arr[0]['total_debit'] = $total_debit;
        $arr[0]['total_kredit'] = $total_kredit;
        $arr[0]['total_saldo'] = $total_debit - $total_kredit;
    }
//    echo "<pre>", print_r($arr), "</pre>";die();
//        foreach ($arr as $key => $val) {
//            $arr[$key] = (array) $val;
//            echo "<pre>", print_r($arr[$key]['detail']), "</pre>";
//        }
//        die();
    $path = 'acc/landaacc/upload/format_buku_besar.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);
    
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 3, "Lokasi : ".$data['lokasi']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 4, "Periode : ".$data['tanggal']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 5, "Disiapkan Pada : ".$data['disiapkan']);

    $row = 7;
    foreach ($arr as $key => $val) {

        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, "Lokasi : ".$data['lokasi']);
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $row, "Nama Akun : ".$val['akun']);
        
        $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row+1), "TANGGAL");
        $objPHPExcel->getActiveSheet()->setCellValue('B' . ($row+1), "REFF");
        $objPHPExcel->getActiveSheet()->setCellValue('C' . ($row+1), "URAIAN");
        $objPHPExcel->getActiveSheet()->setCellValue('D' . ($row+1), "DEBIT");
        $objPHPExcel->getActiveSheet()->setCellValue('E' . ($row+1), "KREDIT");
        $objPHPExcel->getActiveSheet()->setCellValue('F' . ($row+1), "SALDO");
        if (isset($val['detail'])) {
            $rows = $row + 1;
            foreach ((array) $val['detail'] as $keys => $vals) {
                $rows++;
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $rows, $vals['tanggal']);
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $rows, $vals['kode']);
                $objPHPExcel->getActiveSheet()->setCellValue('C' . $rows, $vals['keterangan']);
                $objPHPExcel->getActiveSheet()->setCellValue('D' . $rows, $vals['debit']);
                $objPHPExcel->getActiveSheet()->setCellValue('E' . $rows, $vals['kredit']);
                $objPHPExcel->getActiveSheet()->setCellValue('F' . $rows, $vals['saldo_sekarang']);
            }

            $row = $rows + 2;
        } else {
            $row += 4;
        }
    }

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=laporan_buku_besar.xls");

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});



