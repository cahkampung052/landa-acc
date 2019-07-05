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

$app->post('/acc/l_neraca/laporan', function ($request, $response) {
    $params = $request->getParams();
    $filter = $params;
//    print_r($filter);die();
//    $filter = (array) json_decode($params, true);
    $db = $this->db;



    /** startDate */
    $tanggal_awal = new DateTime($filter['tanggal']);
    $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));

    /** endDate */
//    $tanggal_akhir = new DateTime($filter['tanggal']['endDate']);
//    $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));

    $tanggal_start = $tanggal_awal->format("Y-m-d");
//    $tanggal_end = $tanggal_akhir->format("Y-m-d");

//    $tanggal_ = new Datime($filter['tanggal']);
//    $tanggal_->modify('+1 day');
//    $tanggal = $tanggal_->format("Y-m-d");
//
//    $tanggal_->modify('-1 day');
//    $tgl = $tanggal_->format("Y-m-d");
//    $cabang_id = null;
    
    

    $db->select("
        acc_m_akun.id,
        acc_m_akun.kode,
        acc_m_akun.nama,
        acc_m_akun.level,
        acc_m_akun.is_tipe,
        acc_m_akun.parent_id
        ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode")
            ->customWhere("acc_m_akun.tipe IN('Piutang Usaha','Piutang Lain','Cash & Bank','Persediaan','Harta Tetap', 'Aset Lain', 'Investasi')");
    // ->andWhere("acc_m_akun.is_deleted", "=", 0);

    $modelHarta = $db->findAll();
    $totalHarta = 0;
    $totalSub = 0;
    $arrHarta = [];
    foreach ($modelHarta as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '<=', $tanggal_start);
        $getsaldoawal = $db->find();
        $saldoAwal = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;

        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrHarta[$id]['kode'] = $val->nama;
                $arrHarta[$id]['nama'] = $val->kode . ' - ' . $val->nama;
            } else {
                $id = $val->id;
                $arrHarta[$val->parent_id]['detail'][] = (array) $val;
                $totalHarta += $val->saldo;
            }
        }
    }

    foreach ($arrHarta as $key => $val) {
        $arrHarta[$key] = (array) $val;
        $total = 0;
        if (isset($val['detail'])) {
            if (count($val['detail']) > 0) {
                foreach ($val['detail'] as $vals) {
                    $total += $vals['saldo'];
                }
            }
        }
        $arrHarta[$key]['total'] = $total;
    }
    /** END Harta */
    /** Select Kewajiban */
    $db->select("
        acc_m_akun.id,
        acc_m_akun.kode,
        acc_m_akun.nama,
        acc_m_akun.level,
        acc_m_akun.is_tipe,
        acc_m_akun.parent_id
        ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode")
            ->customWhere("acc_m_akun.tipe IN('Payable')");
    // ->andWhere("acc_m_akun.is_deleted", "=", 0);

    $modelKewajiban = $db->findAll();
    $totalKewajiban = 0;
    $arrKewajiban = [];
    foreach ($modelKewajiban as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '<=', $tanggal_start);
        $getsaldoawal = $db->find();
        $saldoAwal = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;

        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrKewajiban[$id]['kode'] = $val->kode;
                $arrKewajiban[$id]['nama'] = $val->nama;
            } else {
                $arrKewajiban[$val->parent_id]['detail'][] = (array) $val;
                $arrKewajiban[$val->parent_id]['total'] = (isset($arrKewajiban[$val->parent_id]['total']) ? $arrKewajiban[$val->parent_id]['total'] : 0) + $val->saldo;
                $totalKewajiban += $val->saldo;
            }
        }
    }
    foreach ($arrKewajiban as $key => $val) {
        $arrKewajiban[$key] = (array) $val;
        $total = 0;
        if (isset($val['detail'])) {
            if (count($val['detail']) > 0) {
                foreach ($val['detail'] as $vals) {
                    $total += $vals['saldo'];
                }
            }
        }

        $arrKewajiban[$key]['total'] = $total;
    }
    /** END Kewajiban */
    /** Select Modal */
    $db->select("
        acc_m_akun.id,
        acc_m_akun.kode,
        acc_m_akun.nama,
        acc_m_akun.level,
        acc_m_akun.is_tipe,
        acc_m_akun.parent_id
        ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode")
            ->customWhere("acc_m_akun.tipe IN('Modal')");
    // ->andWhere("acc_m_akun.is_deleted", "=", 0);

    $modelModal = $db->findAll();
    
    $arr = getLabaRugi($tanggal_start);
    
//    print_r($arr);die();
    $saldo_labarugi = $arr['PEMASUKAN']['total'] - ($arr['HPP']['total'] + $arr['PENGELUARAN']['total']);
    $totalModal = 0;
    $arrModal = [];
    foreach ($modelModal as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '<=', $tanggal_start);
        $getsaldoawal = $db->find();
        $saldoAwal = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->laba = '';
        if ($val->nama == 'Laba Tahun Berjalan') {
            $val->laba = $saldo_labarugi > 0 || $saldo_labarugi < 0 ? '(' . $saldo_labarugi . ')' : '';
            $saldoAwal += $saldo_labarugi;
        }
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;

        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrModal[$id]['kode'] = $val->nama;
                $arrModal[$id]['nama'] = $val->kode . ' - ' . $val->nama;
            } else {
                $arrModal[$val->parent_id]['detail'][] = (array) $val;
                $arrModal[$val->parent_id]['total'] = (isset($arrModal[$val->parent_id]['total']) ? $arrModal[$val->parent_id]['total'] : 0) + $val->saldo;
                $totalModal += $val->saldo;
            }
        }
    }

    foreach ($arrModal as $key => $val) {
        $arrModal[$key] = (array) $val;
        $total = 0;
        if (isset($val['detail'])) {
            foreach ($val['detail'] as $vals) {
                $total += $vals['saldo'];
            }
        }
        $arrModal[$key]['total'] = $total;
    }

    $totalKewajibanModal = $totalKewajiban + $totalModal;
    /** END Modal */
    return successResponse($response, [
        "modelHarta" =>
        [
            "list" => $arrHarta,
            "total" => $totalHarta,
        ],
        "modelKewajiban" =>
        [
            "list" => $arrKewajiban,
            "total" => $totalKewajiban,
        ],
        "modelModal" =>
        [
            "list" => $arrModal,
            "total" => $totalModal,
            "labarugi" => $saldo_labarugi,
        ],
        "modelKewajibanModal" =>
        [
            "total" => $totalKewajibanModal,
        ],
        "tanggal" => date("d-m-Y", strtotime($tanggal_start)),
        "disiapkan" => date("d-m-Y, H:i")
    ]);
});


$app->get('/acc/l_neraca/exportExcel', function ($request, $response) {

    $params = $request->getParams();
    $filter = $params;
//    print_r($filter);die();
//    $filter = (array) json_decode($params, true);
    $db = $this->db;



    /** startDate */
    $tanggal_awal = new DateTime($filter['tanggal']['startDate']);
    $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));

    /** endDate */
    $tanggal_akhir = new DateTime($filter['tanggal']['endDate']);
    $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));

    $tanggal_start = $tanggal_awal->format("Y-m-d");
    $tanggal_end = $tanggal_akhir->format("Y-m-d");

//    $tanggal_ = new Datime($filter['tanggal']);
//    $tanggal_->modify('+1 day');
//    $tanggal = $tanggal_->format("Y-m-d");
//
//    $tanggal_->modify('-1 day');
//    $tgl = $tanggal_->format("Y-m-d");
//    $cabang_id = null;
    
    

    $db->select("
        acc_m_akun.id,
        acc_m_akun.kode,
        acc_m_akun.nama,
        acc_m_akun.level,
        acc_m_akun.is_tipe,
        acc_m_akun.parent_id
        ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode")
            ->customWhere("acc_m_akun.tipe IN('Piutang Usaha','Piutang Lain','Cash & Bank','Persediaan','Harta Tetap', 'Aset Lain', 'Investasi')");
    // ->andWhere("acc_m_akun.is_deleted", "=", 0);

    $modelHarta = $db->findAll();
    $totalHarta = 0;
    $totalSub = 0;
    $arrHarta = [];
    foreach ($modelHarta as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '<=', $tanggal_start);
        $getsaldoawal = $db->find();
        $saldoAwal = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;

        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrHarta[$id]['kode'] = $val->nama;
                $arrHarta[$id]['nama'] = $val->kode . ' - ' . $val->nama;
            } else {
                $id = $val->id;
                $arrHarta[$val->parent_id]['detail'][] = (array) $val;
                $totalHarta += $val->saldo;
            }
        }
    }

    foreach ($arrHarta as $key => $val) {
        $arrHarta[$key] = (array) $val;
        $total = 0;
        if (isset($val['detail'])) {
            if (count($val['detail']) > 0) {
                foreach ($val['detail'] as $vals) {
                    $total += $vals['saldo'];
                }
            }
        }
        $arrHarta[$key]['total'] = $total;
    }
    /** END Harta */
    /** Select Kewajiban */
    $db->select("
        acc_m_akun.id,
        acc_m_akun.kode,
        acc_m_akun.nama,
        acc_m_akun.level,
        acc_m_akun.is_tipe,
        acc_m_akun.parent_id
        ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode")
            ->customWhere("acc_m_akun.tipe IN('Payable')");
    // ->andWhere("acc_m_akun.is_deleted", "=", 0);

    $modelKewajiban = $db->findAll();
    $totalKewajiban = 0;
    $arrKewajiban = [];
    foreach ($modelKewajiban as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '<=', $tanggal_start);
        $getsaldoawal = $db->find();
        $saldoAwal = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;

        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrKewajiban[$id]['kode'] = $val->kode;
                $arrKewajiban[$id]['nama'] = $val->nama;
            } else {
                $arrKewajiban[$val->parent_id]['detail'][] = (array) $val;
                $arrKewajiban[$val->parent_id]['total'] = (isset($arrKewajiban[$val->parent_id]['total']) ? $arrKewajiban[$val->parent_id]['total'] : 0) + $val->saldo;
                $totalKewajiban += $val->saldo;
            }
        }
    }
    foreach ($arrKewajiban as $key => $val) {
        $arrKewajiban[$key] = (array) $val;
        $total = 0;
        if (isset($val['detail'])) {
            if (count($val['detail']) > 0) {
                foreach ($val['detail'] as $vals) {
                    $total += $vals['saldo'];
                }
            }
        }

        $arrKewajiban[$key]['total'] = $total;
    }
    /** END Kewajiban */
    /** Select Modal */
    $db->select("
        acc_m_akun.id,
        acc_m_akun.kode,
        acc_m_akun.nama,
        acc_m_akun.level,
        acc_m_akun.is_tipe,
        acc_m_akun.parent_id
        ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode")
            ->customWhere("acc_m_akun.tipe IN('Modal')");
    // ->andWhere("acc_m_akun.is_deleted", "=", 0);

    $modelModal = $db->findAll();
    
    $arr = getLabaRugi($tanggal_start, $tanggal_end);
    
//    print_r($arr);die();
    $saldo_labarugi = $arr['PEMASUKAN']['total'] - ($arr['HPP']['total'] + $arr['PENGELUARAN']['total']);
    $totalModal = 0;
    $arrModal = [];
    foreach ($modelModal as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '<=', $tanggal_start);
        $getsaldoawal = $db->find();
        $saldoAwal = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->laba = '';
        if ($val->nama == 'Laba Tahun Berjalan') {
            $val->laba = $saldo_labarugi > 0 || $saldo_labarugi < 0 ? '(' . $saldo_labarugi . ')' : '';
            $saldoAwal += $saldo_labarugi;
        }
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;

        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrModal[$id]['kode'] = $val->nama;
                $arrModal[$id]['nama'] = $val->kode . ' - ' . $val->nama;
            } else {
                $arrModal[$val->parent_id]['detail'][] = (array) $val;
                $arrModal[$val->parent_id]['total'] = (isset($arrModal[$val->parent_id]['total']) ? $arrModal[$val->parent_id]['total'] : 0) + $val->saldo;
                $totalModal += $val->saldo;
            }
        }
    }

    foreach ($arrModal as $key => $val) {
        $arrModal[$key] = (array) $val;
        $total = 0;
        if (isset($val['detail'])) {
            foreach ($val['detail'] as $vals) {
                $total += $vals['saldo'];
            }
        }
        $arrModal[$key]['total'] = $total;
    }

    $totalKewajibanModal = $totalKewajiban + $totalModal;

//    echo '<pre>',print_r($data), '</pre>';
//    echo '<pre>',print_r($arr), '</pre>';die();

    $path = 'acc/landaacc/upload/format_neraca.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);

//    $objPHPExcel->getActiveSheet()->setCellValue('A' . 3, "Lokasi : " . $data['lokasi']);
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 4, "Periode : " . date("d-m-Y", strtotime($tanggal_start)) . ' Sampai ' . date("d-m-Y", strtotime($tanggal_end)));
    $objPHPExcel->getActiveSheet()->setCellValue('A' . 5, "Disiapkan Pada : " . date("d-m-Y, H:i"));

    $objPHPExcel->getActiveSheet()->setCellValue('A' . 7, "Harta");
    
    $row = 8;
    foreach($arrHarta as $key => $val){
        $arrHarta[$key] = (array) $val;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, $val['nama']);
        if(isset($arrHarta[$key]['detail'])){
            $rows = $row+1;
            foreach($arrHarta[$key]['detail'] as $keys => $vals){
                if($vals['is_tipe'] == 0){
                    $objPHPExcel->getActiveSheet()->setCellValue('A' . ($rows), $vals['kode']." ".$vals['nama']);
                    $objPHPExcel->getActiveSheet()->setCellValue('B' . ($rows), $vals['saldo_rp']);
                    $rows++;
                }
            }
            $objPHPExcel->getActiveSheet()->setCellValue('A' . ($rows), "Total ".$val['nama']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . ($rows), $val['total']);
            $row = $rows+1;
        }else{
            $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row+1), "Total ".$val['nama']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . ($row+1), $val['total']);
            $row = $row+2;
        }
        
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, "Total Harta");
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $row, $totalHarta);
    }
    
    $row = $row+1;
    $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, "Kewajiban");
    
    $row = $row+1;
    foreach($arrKewajiban as $key => $val){
        $arrHarta[$key] = (array) $val;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, $val['nama']);
        if(isset($arrHarta[$key]['detail'])){
            $rows = $row+1;
            foreach($arrHarta[$key]['detail'] as $keys => $vals){
                if($vals['is_tipe'] == 0){
                    $objPHPExcel->getActiveSheet()->setCellValue('A' . ($rows), $vals['kode']." ".$vals['nama']);
                    $objPHPExcel->getActiveSheet()->setCellValue('B' . ($rows), $vals['saldo_rp']);
                    $rows++;
                }
            }
            $objPHPExcel->getActiveSheet()->setCellValue('A' . ($rows), "Total ".$val['nama']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . ($rows), $val['total']);
            $row = $rows+1;
        }else{
            $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row+1), "Total ".$val['nama']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . ($row+1), $val['total']);
            $row = $row+2;
        }
        
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, "Total Kewajiban");
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $row, $totalKewajiban);
    }
    
    $row = $row+1;
    $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, "Modals");
    
    $row = $row+1;
    
    foreach($arrModal as $key => $val){
        $arrHarta[$key] = (array) $val;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, $val['nama']);
        if(isset($arrHarta[$key]['detail'])){
            $rows = $row+1;
            foreach($arrHarta[$key]['detail'] as $keys => $vals){
                if($vals['is_tipe'] == 0){
                    $objPHPExcel->getActiveSheet()->setCellValue('A' . ($rows), $vals['kode']." ".$vals['nama']);
                    $objPHPExcel->getActiveSheet()->setCellValue('B' . ($rows), $vals['saldo_rp']);
                    $rows++;
                }
            }
            $objPHPExcel->getActiveSheet()->setCellValue('A' . ($rows), "Total ".$val['nama']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . ($rows), $val['total']);
            $row = $rows+1;
        }else{
            $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row+1), "Total ".$val['nama']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . ($row+1), $val['total']);
            $row = $row+2;
        }
        
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, "Total Modals");
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $row, $totalModal);
    }
    
    $objPHPExcel->getActiveSheet()->setCellValue('A' . ($row+1), "Total Kewajiban dan Modals");
    $objPHPExcel->getActiveSheet()->setCellValue('B' . ($row+1), $totalKewajiban+$totalModal);
    
    

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=laporan_neraca.xls");

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});



