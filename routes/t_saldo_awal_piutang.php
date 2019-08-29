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
            $db->delete("acc_saldo_piutang", ["m_lokasi_id" => $m_lokasi_id]);
            $db->delete("acc_trans_detail", ["m_lokasi_id" => $m_lokasi_id, "reff_type" => "acc_saldo_piutang"]);

            $acc_t = [];
            foreach ($params['detail'] as $val) {
                if (isset($val['total']) && !empty($val['total']) && isset($val['m_akun_id']) && !empty($val['m_akun_id'])) {
                    $detail['m_kontak_id'] = $val['id'];
                    $detail['m_lokasi_id'] = $m_lokasi_id;
                    $detail['m_akun_id'] = $val['m_akun_id']['id'];
                    $detail['tanggal'] = $tanggal;
                    $detail['total'] = $val['total'];


                    $insert = $db->insert('acc_saldo_piutang', $detail);

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
                    $getakun = getPemetaanAkun("Pengimbang Neraca");
                    $detail_['m_kontak_id'] = $val['id'];
                    $detail_['m_lokasi_id'] = $m_lokasi_id;
                    $detail_['m_akun_id'] = $getakun;
                    $detail_['tanggal'] = $tanggal;
                    $detail_['kredit'] = $val['total'];
                    $detail_['reff_type'] = 'acc_saldo_piutang';
                    $detail_['reff_id'] = $insert->id;
                    $detail_['keterangan'] = 'Saldo Piutang';

                    array_push($acc_t, $detail2, $detail_);
                }
            }
            insertTransDetail($acc_t);
//            print_r($acc_t);die();
            return successResponse($response, []);
        }

        return unprocessResponse($response, ['Silahkan buat akun terlebih dahulu']);
    }

    return unprocessResponse($response, ['Tanggal tidak boleh kosong']);
});

/**
 * export
 */
$app->get('/acc/t_saldo_awal_piutang/exportPiutangAwal', function ($request, $response) {

    /*
     * ambil tanggal setting
     */
    $db = $this->db;
    $tanggalsetting = $db->select("*")->from("acc_m_setting")->find();
    $tanggalsetting = date("Y-m-d", strtotime($tanggalsetting->tanggal . ' -1 day'));

    $lokasi = $db->select("*")->from("acc_m_lokasi")->orderBy("kode")->findAll();

    $akun = $db->select("*")->from("acc_m_akun")->where("is_deleted", "=", 0)->where("is_tipe", "=", 0)->orderBy("kode")->findAll();

    $customer = $db->select("*")->from("acc_m_kontak")->where("is_deleted", "=", 0)->where("type", "=", "customer")->findAll();

    $path = 'acc/landaacc/file/format_saldo_piutang.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);

    $objPHPExcel->getActiveSheet()->setCellValue('G' . 3, $tanggalsetting);
    $objPHPExcel->getActiveSheet()->setCellValue('K' . 3, $lokasi[0]->id);
    $objPHPExcel->getActiveSheet()->setCellValue('K' . 4, $tanggalsetting);

    $rowl = 4;
    foreach ($lokasi as $key => $val) {

        $objPHPExcel->getActiveSheet()->setCellValue('A' . $rowl, $val->id);
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $rowl, $val->kode . " - " . $val->nama);
        $rowl++;
    }

    $row = 4;
    foreach ($akun as $key => $val) {

        $objPHPExcel->getActiveSheet()->setCellValue('D' . $row, $val->id);
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $row, $val->kode . " - " . $val->nama);
        $row++;
    }

    $rows = 6;
    foreach ($customer as $key => $val) {

        $objPHPExcel->getActiveSheet()->setCellValue('J' . $rows, $val->id);
        $objPHPExcel->getActiveSheet()->setCellValue('K' . $rows, $val->nama);
        $objPHPExcel->getActiveSheet()->setCellValue('L' . $rows, $akun[0]->id);
        $objPHPExcel->getActiveSheet()->setCellValue('M' . $rows, 0);
        $rows++;
    }

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=format_saldo_piutang.xls");

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});

/**
 * import
 */
$app->post('/acc/t_saldo_awal_piutang/importPiutangAwal', function ($request, $response) {
    $db = $this->db;
    if (!empty($_FILES)) {
        $tempPath = $_FILES['file']['tmp_name'];
        $newName = urlParsing($_FILES['file']['name']);
        $inputFileName = "acc/landaacc/file/" . DIRECTORY_SEPARATOR . $newName;
        move_uploaded_file($tempPath, $inputFileName);
        if (file_exists($inputFileName)) {
            try {
                $inputFileType = PHPExcel_IOFactory::identify($inputFileName);
                $objReader = PHPExcel_IOFactory::createReader($inputFileType);
                $objPHPExcel = $objReader->load($inputFileName);
            } catch (Exception $e) {
                die('Error loading file "' . pathinfo($inputFileName, PATHINFO_BASENAME) . '": ' . $e->getMessage());
            }
            $sheet = $objPHPExcel->getSheet(0);
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();

            $customer = $db->select("*")->from("acc_m_kontak")->where("is_deleted", "=", 0)->where("type", "=", "customer")->findAll();
            $row = 6;
            $models = [];
            foreach ($customer as $key => $val) {
                $akun = $db->select("*")->from("acc_m_akun")->where("id", "=", $objPHPExcel->getSheet(0)->getCell('L' . $row)->getValue())->find();

                $models[$key] = (array) $val;
                $models[$key]['m_akun_id'] = (array) $akun;
                $models[$key]['total'] = $objPHPExcel->getSheet(0)->getCell('M' . $row)->getValue();

                $row++;
            }

            unlink($inputFileName);

            $data['lokasi'] = $db->select("*")->from("acc_m_lokasi")->where("id", "=", $objPHPExcel->getSheet(0)->getCell('K' . 3)->getValue())->find();
            $data['tanggal'] = $objPHPExcel->getSheet(0)->getCell('K' . 4)->getValue();
            return successResponse($response, ['data' => $data, 'detail' => $models]);
        } else {
            return unprocessResponse($response, 'data gagal di import');
        }
    }
});
