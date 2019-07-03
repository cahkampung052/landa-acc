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

$app->post('/acc/l_jurnal_umum/laporan', function ($request, $response) {
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



        $arr = [];
        $data['total_debit'] = 0;
        $data['total_kredit'] = 0;
        
        $index=0;

        $getakun = $sql->select("*")
                ->from("acc_m_akun")
                ->where("is_tipe", "=", 0)
                ->where("is_deleted", "=", 0)
                ->orderBy("kode")
                ->findAll();
        foreach ($getakun as $key => $val) {

            $sql->select("acc_trans_detail.kode, acc_trans_detail.debit, acc_trans_detail.kredit, acc_trans_detail.keterangan, acc_trans_detail.tanggal, acc_m_akun.kode as kodeAkun, acc_m_akun.nama")
                    ->from("acc_trans_detail")
                    ->join("join", "acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
                    ->where('acc_trans_detail.m_akun_id', '=', $val->id)
                    ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                    ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
            if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                $sql->andWhere('acc_trans_detail.m_lokasi_id', '=', $params['m_lokasi_id']['id']);
            }
            $gettransdetail = $sql->findAll();
            foreach($gettransdetail as $keys => $vals){
                if($vals->debit == NULL){
                    $vals->debit = 0;
                }
                if($vals->kredit == NULL){
                    $vals->kredit = 0;
                }

                $arr[$index] = $vals;
                $index++;
                $data['total_debit'] += $vals->debit;
                $data['total_kredit'] += $vals->kredit;
//                echo $vals->debit . "/";
            }
            
            
        }
//        die();
        function cmp($a, $b)
        {
            return strcmp($a->tanggal, $b->tanggal);
        }

        usort($arr, "cmp");
//        echo json_encode($arr);die();

        return successResponse($response, ["data" => $data, "detail" => $arr]);
    } else {
        return unprocessResponse($response, $validasi);
    }
});


$app->get('/acc/l_jurnal_umum/exportExcel', function ($request, $response) {

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
        $data['total_debit'] = 0;
        $data['total_kredit'] = 0;
        
        $index=0;

        $getakun = $sql->select("*")
                ->from("acc_m_akun")
                ->where("is_tipe", "=", 0)
                ->where("is_deleted", "=", 0)
                ->orderBy("kode")
                ->findAll();
        foreach ($getakun as $key => $val) {

            $sql->select("acc_trans_detail.kode, acc_trans_detail.debit, acc_trans_detail.kredit, acc_trans_detail.keterangan, acc_trans_detail.tanggal, acc_m_akun.kode as kodeAkun, acc_m_akun.nama")
                    ->from("acc_trans_detail")
                    ->join("join", "acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
                    ->where('acc_trans_detail.m_akun_id', '=', $val->id)
                    ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                    ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
            if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                $sql->andWhere('acc_trans_detail.m_lokasi_id', '=', $params['m_lokasi_id']['id']);
            }
            $gettransdetail = $sql->findAll();
            foreach($gettransdetail as $keys => $vals){
                if($vals->debit == NULL){
                    $vals->debit = 0;
                }
                if($vals->kredit == NULL){
                    $vals->kredit = 0;
                }

                $arr[$index] = $vals;
                $index++;
                $data['total_debit'] += $vals->debit;
                $data['total_kredit'] += $vals->kredit;
//                echo $vals->debit . "/";
            }
            
            
        }
//        die();
        function cmp($a, $b)
        {
            return strcmp($a->tanggal, $b->tanggal);
        }

        usort($arr, "cmp");
        
        
    $path = 'acc/landaacc/upload/format_jurnal_umum.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);

    $objPHPExcel->getActiveSheet()->setCellValue('A' . 3, "Lokasi : " . $data['lokasi']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 4, "Periode : " . $data['tanggal']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 5, "Disiapkan Pada : " . $data['disiapkan']);

    $row = 8;
    foreach ($arr as $key => $val) {

        $row++;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, $val->tanggal);
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $row, $val->kode);
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $row, $val->nama);
        $objPHPExcel->getActiveSheet()->setCellValue('D' . $row, $val->keterangan);
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $row, $val->debit);
        $objPHPExcel->getActiveSheet()->setCellValue('F' . $row, $val->kredit);
           
    }

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=laporan_jurnal_umum.xls");

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});



