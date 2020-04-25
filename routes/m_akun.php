<?php

/**
 * validasi akun
 */
function validasi($data, $custom = array()) {
    $validasi = array(
        'tipe' => 'required',
        'kode' => 'required',
        'nama' => 'required',
        'is_kas' => 'required',
    );
    GUMP::set_field_name("parent_id", "Akun Induk");
    GUMP::set_field_name("is_kas", "Kas");
    GUMP::set_field_name("is_tipe", "Sub Akun");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

/**
 * validasi saldo awal
 */
function validasiSaldo($data, $custom = array()) {
    $validasi = array(
        'tanggal' => 'required',
        'm_lokasi_id' => 'required',
    );
    GUMP::set_field_name("m_lokasi_id", "Lokasi");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

/**
 * setLevelTipeAkun
 */
function setLevelTipeAkun($parent_id) {
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $parent = $db->find("select * from acc_m_akun where id = '" . $parent_id . "'");
    return $parent->level + 1;
}

/*
 * get kode
 */
$app->get('/acc/m_akun/getKode/{kode}', function ($request, $response) {
    $kode = $request->getAttribute('kode');
    $db = $this->db;
    $models = $db->select('kode')->from('acc_m_akun')->where('kode', '=', $kode)->count();
    if ($models > 0) {
        return successResponse($response, ['status_kode' => 0, 'message' => "Kode sudah digunakan"]);
    } else {
        return successResponse($response, ['status_kode' => 1, 'message' => ""]);
    }
});
/**
 * Ambil saldo awal
 */
$app->get('/acc/m_akun/getSaldoAwal', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $tanggal = $params['tanggal'];
    /**
     * List akun
     */
    $db->select("acc_m_akun.*, sum(acc_trans_detail.debit) as debit, sum(acc_trans_detail.kredit) as kredit, acc_trans_detail.tanggal")
            ->from("acc_m_akun")
            ->leftJoin("acc_trans_detail", "acc_trans_detail.m_lokasi_id = '" . $params["m_lokasi_id"] . "' and m_akun_id = acc_m_akun.id and reff_type = 'Saldo Awal'")
            ->orderBy('acc_m_akun.kode')
            ->groupBy("acc_m_akun.id");
    $models = $db->findAll();
    $arr = [];
    foreach ($models as $key => $value) {
        $saldo = isset($arrTrans[$value->id]) ? $arrTrans[$value->id] : 0;
        $spasi = ($value->level == 1) ? '' : str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $value->level - 1);
        $arr[$key] = (array) $value;
        $arr[$key]['nama_lengkap'] = $spasi . $value->kode . ' - ' . $value->nama;
        $arr[$key]['debit'] = empty($value->debit) ? 0 : $value->debit;
        $arr[$key]['kredit'] = empty($value->kredit) ? 0 : $value->kredit;
    }
    return successResponse($response, ['detail' => $arr, 'tanggal' => $tanggal]);
});
/**
 * Simpan saldo awal
 */
$app->post('/acc/m_akun/saveSaldoAwal', function ($request, $response) {
    $params = $request->getParams();
    $validasi = validasiSaldo($params['form']);
    if ($validasi === true) {
        $tanggal = new DateTime($params['form']['tanggal']);
        $tanggal->setTimezone(new DateTimeZone('Asia/Jakarta'));
        $tanggal = $tanggal->format("Y-m-d");
        $m_lokasi_id = $params['form']['m_lokasi_id']['id'];
        if (!empty($params['detail'])) {
            $db = $this->db;
            /**
             * Delete saldo awal di trans detail
             */
            $delete = $db->delete('acc_trans_detail', ['m_lokasi_id' => $m_lokasi_id, 'keterangan' => 'Saldo Awal', 'reff_type' => 'Saldo Awal']);
            /**
             * Masukkan saldo awal
             */
            if ($delete) {
                foreach ($params['detail'] as $val) {
                    if ((isset($val['debit']) && !empty($val['debit'])) || (isset($val['kredit']) && !empty($val['kredit']))) {
                        $detail['m_lokasi_jurnal_id'] = $m_lokasi_id;
                        $detail['m_lokasi_id'] = $m_lokasi_id;
                        $detail['tanggal'] = $tanggal;
                        $detail['reff_type'] = 'Saldo Awal';
                        $detail['keterangan'] = 'Saldo Awal';
                        $detail['debit'] = !empty($val['debit']) ? $val['debit'] : 0;
                        $detail['kredit'] = !empty($val['kredit']) ? $val['kredit'] : 0;
                        $detail['m_akun_id'] = $val['id'];
                        $db->insert('acc_trans_detail', $detail);
                    }
                }
                return successResponse($response, []);
            }
        }
        return unprocessResponse($response, ['Silahkan buat akun terlebih dahulu']);
    } else {
        return unprocessResponse($response, $validasi);
    }
});
/**
 * export
 */
$app->get('/acc/m_akun/exportSaldoAwal', function ($request, $response) {
    /*
     * ambil tanggal setting
     */
    $db = $this->db;
    $tanggalsetting = $db->select("*")->from("acc_m_setting")->find();
    $tanggalsetting = date("Y-m-d", strtotime($tanggalsetting->tanggal . ' -1 day'));
    $lokasi = $db->select("*")->from("acc_m_lokasi")->orderBy("kode")->findAll();
    $akun = $db->select("*")->from("acc_m_akun")->where("is_deleted", "=", 0)->orderBy("kode")->findAll();
    $path = 'acc/landaacc/file/format_saldo_awal.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);
    $objPHPExcel->getActiveSheet()->setCellValue('D' . 3, $tanggalsetting);
    $row = 4;
    foreach ($lokasi as $key => $val) {
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, $val->id);
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $row, $val->kode . " - " . $val->nama);
        $row++;
    }
    $objPHPExcel->getActiveSheet()->setCellValue('H' . 3, $lokasi[0]->id);
    $objPHPExcel->getActiveSheet()->setCellValue('H' . 4, $tanggalsetting);
    $rows = 6;
    foreach ($akun as $key => $val) {
        $spasi = ($val->level == 1) ? '' : str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $val->level - 1);
        $val->nama_lengkap = $spasi . $val->kode . ' - ' . $val->nama;
        $objPHPExcel->getActiveSheet()->setCellValue('G' . $rows, $val->id);
        $objPHPExcel->getActiveSheet()->setCellValue('H' . $rows, $val->nama_lengkap);
        if ($val->is_tipe == 0) {
            $objPHPExcel->getActiveSheet()->setCellValue('I' . $rows, 0);
            $objPHPExcel->getActiveSheet()->setCellValue('J' . $rows, 0);
        }
        $rows++;
    }
    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=format_saldo_awal.xls");
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});
/**
 * import
 */
$app->post('/acc/m_akun/importSaldoAwal', function ($request, $response) {
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
            $models = [];
            for ($row = 6; $row <= $highestRow; $row++) {
                $akun = $db->select("*")->from("acc_m_akun")->where("id", "=", $objPHPExcel->getSheet(0)->getCell('G' . $row)->getValue())->find();
                $models[$row] = (array) $akun;
                $spasi = ($akun->level == 1) ? '' : str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $akun->level - 1);
                $models[$row]['nama_lengkap'] = $spasi . $akun->kode . ' - ' . $akun->nama;
                $models[$row]['debit'] = $objPHPExcel->getSheet(0)->getCell('I' . $row)->getValue();
                $models[$row]['kredit'] = $objPHPExcel->getSheet(0)->getCell('J' . $row)->getValue();
            }
            unlink($inputFileName);
            $data['lokasi'] = $db->select("*")->from("acc_m_lokasi")->where("id", "=", $objPHPExcel->getSheet(0)->getCell('H' . 3)->getValue())->find();
            $data['tanggal'] = $objPHPExcel->getSheet(0)->getCell('H' . 3)->getValue();
            return successResponse($response, ['data' => $data, 'detail' => $models]);
        } else {
            return unprocessResponse($response, 'data gagal di import');
        }
    }
});
/**
 * Ambil daftar akun
 */
$app->get('/acc/m_akun/index', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    /*
     * Ambil semua lokasi
     */
    $lokasiId = [];
    $lokasi = $db->select("id")
        ->from("acc_m_lokasi")
        ->where("is_deleted", "=", 0)
        ->findAll();
    foreach ($lokasi as $key => $value) {
        $lokasiId[] = $value->id;
    }
    $lokasiId = implode(",", $lokasiId);
    /**
     * Ambil transaksi di akun
     */
    $db->select("
            SUM(acc_trans_detail.debit) as debit,
            SUM(acc_trans_detail.kredit) as kredit,
            acc_m_akun.id,
            acc_m_akun.saldo_normal,
            acc_m_akun.parent_id,
            acc_m_akun.nama,
            acc_m_akun.kode
        ")
            ->from("acc_m_akun")
            ->leftJoin("acc_trans_detail", "acc_m_akun.id = acc_trans_detail.m_akun_id")
            ->customWhere("m_lokasi_id in (" . $lokasiId . ")")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.is_tipe ASC, parent_id DESC, acc_m_akun.level DESC");
    $trans = $db->findAll();
    $arrTrans = [];
    foreach ($trans as $key => $value) {
        $value->kredit = (!empty($value->kredit)) ? (int) $value->kredit : 0;
        $value->debit = (!empty($value->debit)) ? (int) $value->debit : 0;

        $arrTrans[$value->id] = ((isset($arrTrans[$value->id])) ? $arrTrans[$value->id] : 0) + (intval($value->debit) - intval($value->kredit)) * $value->saldo_normal;
        $arrTrans[$value->parent_id] = ((isset($arrTrans[$value->parent_id])) ? $arrTrans[$value->parent_id] : 0) + $arrTrans[$value->id];
    }

    //echo json_encode($trans);exit();
    /**
     * List akun
     */
    $db->select("acc_m_akun.*, induk.nama as nama_induk, induk.kode as kode_induk")
            ->from("acc_m_akun")
            ->leftJoin("acc_m_akun as induk", "induk.id = acc_m_akun.parent_id")
            ->orderBy('acc_m_akun.kode');
    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_m_akun.is_deleted", '=', $val);
            } elseif ($key == 'kode') {
                $db->where("acc_m_akun.kode", 'LIKE', $val);
            } elseif ($key == 'nama') {
                $db->where("acc_m_akun.nama", 'LIKE', $val);
            } else {
                $db->where($key, 'LIKE', $val);
            }
        }
    }
    $models = $db->findAll();
    $totalItem = $db->count();
    $listAkun = buildTreeAkun($models, 0);
    $arrModel = flatten($listAkun);

    $arr = [];
    foreach ($arrModel as $key => $value) {
        $saldo = isset($arrTrans[$value->id]) ? $arrTrans[$value->id] : 0;
        $spasi = ($value->level == 1) ? '' : str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $value->level - 1);
        $arr[$key] = (array) $value;
        $arr[$key]['nama'] = $value->nama;
        $arr[$key]['nama_lengkap'] = $spasi . $value->kode . ' - ' . $value->nama;
        $arr[$key]['parent_id'] = (int) $value->parent_id;
        $arr[$key]['is_induk'] = (int) $value->is_induk;
        $arr[$key]['saldo_normal'] = (int) $value->saldo_normal;
        $arr[$key]['is_kas'] = (int) $value->is_kas;
        $arr[$key]['kode'] = str_replace($value->kode_induk . "", "", $value->kode);
        $arr[$key]['saldo'] = $saldo;
        $arr[$key]['tipe'] = ($value->tipe == 'No Type') ? '' : $value->tipe;
    }
    return successResponse($response, ['list' => $arr, 'totalItems' => $totalItem]);
});
/**
 * Ambil list akun
 */
$app->get('/acc/m_akun/listakun', function ($request, $response) {
    $sql = $this->db;
    $data = $sql->findAll('select * from acc_m_akun where is_deleted = 0 order by kode');
    foreach ($data as $key => $val) {
        $data[$key] = (array) $val;
        $spasi = ($val->level == 1) ? '' : str_repeat("--", $val->level - 1);
        $data[$key]['nama_lengkap'] = $spasi . $val->kode . ' - ' . $val->nama;
    }
    return successResponse($response, $data);
});
/**
 * Simpan akun
 */
$app->post('/acc/m_akun/save', function ($request, $response) {
    $data = $request->getParams();
    $sql = $this->db;
    $id = isset($data['id']) ? $data['id'] : '';
    $data['tipe'] = isset($data['tipe']) ? $data['tipe'] : '';
    $data['parent_id'] = isset($data['parent_id']) ? $data['parent_id'] : '';
    $data['is_tipe'] = isset($data['is_tipe']) ? $data['is_tipe'] : 0;
    $data['is_induk'] = isset($data['is_induk']) ? $data['is_induk'] : 0;
    $data['kode'] = isset($data['kode']) ? $data['kode'] : '';
    if ($data['is_induk'] == 0) {
        $validasi = validasi($data, ["parent_id" => "required"]);
    } else {
        $validasi = validasi($data);
    }
    if ($validasi === true) {
        if ($data['is_induk'] == 0) {
            $data['kode'] = $data['parent_id'] == 0 ? $data['kode'] : $data['kode_induk'] . '' . $data['kode'];
        }

        /**
         * Cek kode
         */
        $cekKode = $sql->select("kode, nama")
                ->from("acc_m_akun")
                ->where("kode", "=", $data['kode'])
                ->andWhere("id", "!=", $id)
                ->find();
        if (isset($cekKode->kode)) {
            return unprocessResponse($response, ["kode sudah digunakan untuk akun '" . $cekKode->nama . "'"]);
        }
        /**
         * Set level dan tipe arus kas
         */
        if ($data['parent_id'] == 0) {
            $data['level'] = 1;
        } else {
            $data['level'] = setLevelTipeAkun($data['parent_id']);
            /**
             * Update is_tipe akun di atasnya
             */
            $sql->update("acc_m_akun", ["is_tipe" => 1], ["id" => $data['parent_id']]);
        }
        /**
         * Simpan ke database
         */
        if (isset($data['id']) && !empty($data['id'])) {
            $model = $sql->update("acc_m_akun", $data, ["id" => $data["id"]]);
        } else {
            $model = $sql->insert("acc_m_akun", $data);
        }
        /**
         * Update saldo Normal
         */
        $sql->run("update acc_m_akun set saldo_normal = 1 where tipe = 'HARTA'");
        $sql->run("update acc_m_akun set saldo_normal = -1 where tipe = 'KEWAJIBAN'");
        $sql->run("update acc_m_akun set saldo_normal = -1 where tipe = 'MODAL'");
        $sql->run("update acc_m_akun set saldo_normal = -1 where tipe = 'PENDAPATAN'");
        $sql->run("update acc_m_akun set saldo_normal = -1 where tipe = 'PENDAPATAN DILUAR USAHA'");
        $sql->run("update acc_m_akun set saldo_normal = 1 where tipe = 'BEBAN'");
        $sql->run("update acc_m_akun set saldo_normal = 1 where tipe = 'BEBAN DILUAR USAHA'");
        /**
         * Update tipe akun dibawahnya
         */
        $childId = getChildId("acc_m_akun", $model->id);
        if (!empty($childId)) {
            $sql->update("acc_m_akun", ["tipe" => $model->tipe, "tipe_arus" => $model->tipe_arus, "is_kas" => $model->is_kas], "id in (" . implode(",", $childId) . ")");
            /**
             * Jika punya child berarti is_tipe = 1
             */
            $sql->update("acc_m_akun", ["is_tipe" => 1], ["id" => $model->id]);
        } else {
            /**
             * Jika punya child berarti is_tipe = 0
             */
            $sql->update("acc_m_akun", ["is_tipe" => 0], ["id" => $model->id]);
        }
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, $validasi);
    }
});
/**
 * Hapus akun
 */
$app->post('/acc/m_akun/trash', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $update['is_deleted'] = $data['is_deleted'];
    if ($data['is_deleted'] == 1) {
        $update['tgl_nonaktif'] = date('Y-m-d');
    }

    if (isset($data['tipe_arus']) && !empty($data['tipe_arus'])) {
        $update['tipe_arus'] = $data['tipe_arus'];
    }

    $model = $db->update("acc_m_akun", $update, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
/**
 * import
 */
$app->post('/acc/m_akun/import', function ($request, $response) {
    $db = $this->db;
    if (!empty($_FILES)) {
        $tempPath = $_FILES['file']['tmp_name'];
        $newName = urlParsing($_FILES['file']['name']);
        $inputFileName = "./upload" . DIRECTORY_SEPARATOR . $newName;
        move_uploaded_file($tempPath, $inputFileName);
        if (file_exists($inputFileName)) {
            try {
                $inputFileType = PHPExcel_IOFactory::identify($inputFileName);
                $objReader = PHPExcel_IOFactory::createReader($inputFileType);
                $objPHPExcel = $objReader->load($inputFileName);
            } catch (Exception $e) {
                die('Error loading file "' . pathinfo($inputFileName, PATHINFO_BASENAME) . '": ' . $e->getMessage());
            }
            /*
             * get parent_id
             */
            $parentId = [];
            $parent = $db->select("kode")->from("acc_m_akun")->where("is_tipe", "=", 1)->findAll();
            foreach ($parent as $key => $val) {
                $parentId[] = $val->kode;
            }
            $sheet = $objPHPExcel->getSheet(0);
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            for ($row = 11; $row <= $highestRow; $row++) {
                $kode_induk = $objPHPExcel->getSheet(0)->getCell('D' . $row)->getValue();
                if (isset($kode_induk) && !in_array($kode_induk, $parentId)) {
                    $parentId[] = $kode_induk;
                }
            }

//            pd($parentId);
            for ($row = 11; $row <= $highestRow; $row++) {
                $kode = $objPHPExcel->getSheet(0)->getCell('B' . $row)->getValue();
                if (isset($kode)) {
                    $data = [];
                    $data['kode'] = $kode;
                    $data['nama'] = $objPHPExcel->getSheet(0)->getCell('C' . $row)->getValue();
                    $data['level'] = 1;
                    $data['is_induk'] = 1;
                    /*
                     * ambil id dari kode induk
                     */
                    $kode_induk = $objPHPExcel->getSheet(0)->getCell('D' . $row)->getValue();
                    if (isset($kode_induk) && !empty($kode_induk) && strlen($kode_induk) > 0) {
                        $model = $db->select("*")->from("acc_m_akun")->where("kode", "=", $kode_induk)->find();
                        if ($model) {
                            $data['parent_id'] = $model->id;
                            $data['level'] = $model->level + 1;
                            $data['is_induk'] = 0;
                        }
                    }
                    $data['tipe'] = $objPHPExcel->getSheet(0)->getCell('E' . $row)->getValue();
                    /*
                     * tipe arus kas
                     */
                    $tipe_arus = $objPHPExcel->getSheet(0)->getCell('F' . $row)->getValue();
                    if (isset($tipe_arus)) {
                        if ($tipe_arus == "AO") {
                            $data['tipe_arus'] = "Aktivitas Operasi";
                        } elseif ($tipe_arus == "IN") {
                            $data['tipe_arus'] = "Investasi";
                        } elseif ($tipe_arus == "PD") {
                            $data['tipe_arus'] = "Pendanaan";
                        }
                    }
                    /*
                     * saldo normal
                     */
                    $saldo_normal = $objPHPExcel->getSheet(0)->getCell('G' . $row)->getValue();
                    if (isset($saldo_normal)) {
                        if ($saldo_normal == "D") {
                            $data['saldo_normal'] = 1;
                        } elseif ($saldo_normal == "K") {
                            $data['saldo_normal'] = -1;
                        }
                    }
                    if (in_array($kode, $parentId)) {
                        $data['is_tipe'] = 1;
                    } else {
                        $data['is_tipe'] = 0;
                    }
                    $data['is_deleted'] = 0;
                    $tes[] = $data;
                    $cekkode = $db->select("*")->from("acc_m_akun")->where("kode", "=", $kode)->find();
                    if ($cekkode) {
                        $update = $db->update("acc_m_akun", $data, ["kode" => $kode]);
                    } else {
                        $insert = $db->insert("acc_m_akun", $data);
                    }
                }
            }

//            pd($tes);
            unlink($inputFileName);
            return successResponse($response, 'data berhasil di import');
        } else {
            return unprocessResponse($response, 'data gagal di import');
        }
    }
});
/**
 * Export
 */
$app->get('/acc/m_akun/export', function ($request, $response) {
    $db = $this->db;
    $path = 'acc/landaacc/file/format_masterakun.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);
    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=format_akun.xls");
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});
/*
 * ambil budget per lokasi (approve proposal)
 */
$app->get("/acc/m_akun/getBudgetPerLokasi", function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $getBudget = $db->select("*")
            ->from("acc_budgeting")
            ->where("tahun", "=", $params['tahun'])
            ->andWhere("m_lokasi_id", "=", $params['m_lokasi_id'])
            ->findAll();
    $arr = [];
    for ($i = 1; $i <= 12; $i++) {
        $arr[$i]['budget'] = 0;
        $arr[$i]['nama_bulan'] = date('F', mktime(0, 0, 0, $i, 10)); // March
    }
    foreach ($getBudget as $key => $val) {
        $arr[$val->bulan]['budget'] += $val->budget;
    }
    return successResponse($response, $arr);
});
/**
 * Ambil budget
 */
$app->get('/acc/m_akun/getBudget', function ($request, $response) {
    $params = $request->getParams();

    $start_month = $params['start'] . "-01";
    $end_month = date("Y-m-d", strtotime('+1 month', strtotime($params['end'] . "-01")));
    $current_month = $start_month;

    $name_month = [];
    $all_year = [];
    $all_month = [];

    do {
        if (!in_array(date("Y", strtotime($current_month)), $all_year)) {
            $all_year[] = (int) date("Y", strtotime($current_month));
        }

        if (!in_array(date("m", strtotime($current_month)), $all_month)) {
            $all_month[] = (int) date("m", strtotime($current_month));
        }

        $name_month[date("m-Y", strtotime($current_month))]["name"] = date("F Y", strtotime($current_month));
        $name_month[date("m-Y", strtotime($current_month))]["detail"] = ["budget" => 0];
        $name_month[date("m-Y", strtotime($current_month))]["date"] = $current_month;
        $current_month = date("Y-m-d", strtotime('+1 month', strtotime($current_month)));
    } while ($current_month != $end_month);

//    echo json_encode($all_year);
//    die;

    $db = $this->db;
    $getBudget = $db->select("*")
            ->from("acc_budgeting")
            ->customWhere("tahun IN (" . implode(", ", $all_year) . ")", "AND")
            ->customWhere("bulan IN (" . implode(", ", $all_month) . ")", "AND")
            ->andWhere("m_akun_id", "=", $params['m_akun_id'])
            ->andWhere("m_lokasi_id", "=", $params['m_lokasi_id'])
            // ->andWhere("m_kategori_pengajuan_id", "=", $params['m_kategori_pengajuan_id'])
            ->findAll();

//    echo json_encode($getBudget);die;
    $list = $name_month;
    foreach ($getBudget as $key => $value) {
        if ($value->bulan < 10) {
            $value->bulan = 0 . "" . $value->bulan;
        }
        $list[$value->bulan . "-" . $value->tahun]['detail'] = (array) $value;
    }

//    echo json_encode($list);die;
//    $listBudget = [];
//    for ($i = 1; $i <= 12; $i++) {
//        $j = $i;
//        $listBudget[$i]['id'] = isset($list[$j]) ? $list[$j]['id'] : null;
//        $listBudget[$i]['budget'] = isset($list[$j]) ? $list[$j]['budget'] : 0;
//        $listBudget[$i]['nama_bulan'] = date('F', mktime(0, 0, 0, $i, 10)); // March
//    }
    return successResponse($response, $list);
});
/**
 * Simpan budget
 */
$app->post('/acc/m_akun/saveBudget', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    try {
        foreach ($params['detail'] as $key => $value) {
            $data = [
                'm_akun_id' => $params['form']['m_akun_id']['id'],
                'm_lokasi_id' => $params['form']['m_lokasi_id']['id'],
                // 'm_kategori_pengajuan_id' => $params['form']['m_kategori_pengajuan_id']['id'],
                'bulan' => date('m', strtotime($value['date'])),
                'tahun' => date('Y', strtotime($value['date'])),
                'budget' => $value['detail']['budget']
            ];
            $cek = $db->select("id")
                        ->from("acc_budgeting")
                        ->where("m_akun_id", "=", $params['form']['m_akun_id']['id'])
                        ->andWhere("m_akun_id", "=", $params['form']['m_lokasi_id']['id'])
                        ->andWhere("bulan", "=", date('m', strtotime($value['date'])))
                        ->andWhere("tahun", "=", date('Y', strtotime($value['date'])))
                        ->find();
            if (isset($cek->id) && !empty($cek->id)) {
                $db->update('acc_budgeting', $data, ['id' => $cek->id]);
            } else {
                $db->insert('acc_budgeting', $data);
            }
        }
        return successResponse($response, []);
    } catch (Exception $e) {
        return unprocessResponse($response, $e);
    }
});
/**
 * Ambil semua akun
 */
$app->get('/acc/m_akun/akunAll', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->where("is_deleted", "=", 0)
            ->findAll();
    return successResponse($response, ['list' => $models]);
});
/**
 * Ambil akun kas
 */
$app->get('/acc/m_akun/akunKas', function ($request, $response) {
    $db = $this->db;
    $params = $request->getParams();
    $db->select("*")->from("acc_m_akun")
            ->where("is_kas", "=", 1)
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0);
    if (isset($params['nama']) && !empty($params['nama'])) {
        $db->customWhere("acc_m_akun.nama LIKE '%" . $params['nama'] . "%'", "AND");
    }
    $models = $db->findAll();
    return successResponse($response, ['list' => $models]);
});
/**
 * Ambil akun berdasarkan tipe
 */
$app->get('/acc/m_akun/getByType', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $tipe = isset($params['tipe']) ? $params['tipe'] : '';
    if (!empty($tipe)) {
        $db->select("*")
                ->from("acc_m_akun")
                ->where("is_deleted", "=", 0)
                ->andWhere("tipe", "=", $tipe);
        $models = $db->findAll();
        $arr = [];
        foreach ($models as $key => $value) {
            $value->nama = $value->nama;
            $saldo = getSaldo($value->id, null, null, date("Y-m-d"));
            if ($saldo <= 0) {
                $arr[] = (array) $value;
            }
        }
        return successResponse($response, ['list' => $arr]);
    } else {
        return successResponse($response, ['list' => []]);
    }
});
/*
 * Ambil akun pendapatan
 */
$app->get('/acc/m_akun/akunPendapatan', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->customWhere("nama LIKE '%PENDAPATAN%'")
            ->andWhere("tipe", "=", "PENDAPATAN")
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0)
            ->findAll();
    return successResponse($response, ['list' => $models]);
});
/*
 * Ambil akun hutang
 */
$app->get('/acc/m_akun/akunHutang', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")
            ->from("acc_m_akun")
            ->where("is_tipe", "=", 0)
            ->andWhere("is_deleted", "=", 0)
            ->andWhere("tipe", "=", "KEWAJIBAN")
            ->findAll();

    foreach ($models as $key => $value) {
        $value->nama = $value->nama;
    }

    return successResponse($response, ['list' => $models]);
});
/*
 * Ambil akun hutang
 */
$app->get('/acc/m_akun/akunPiutang', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->customWhere("nama LIKE '%PIUTANG%'")
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0)
            ->findAll();

    foreach ($models as $key => $value) {
        $value->nama = $value->nama;
    }

    return successResponse($response, ['list' => $models]);
});
/**
 * Ambil akun beban
 */
$app->get('/acc/m_akun/akunBeban', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->where("tipe", "=", "BEBAN")
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0)
            ->findAll();

    foreach ($models as $key => $value) {
        $value->nama = $value->nama;
        $spasi = ($value->level == 1) ? '' : str_repeat("--", $value->level - 1);
        $value->nama_lengkap = $spasi . $value->kode . ' - ' . $value->nama;
    }

    return successResponse($response, ['list' => $models]);
});

$app->get('/acc/m_akun/akunBebanPendapatan', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->customWhere("tipe = 'PENDAPATAN' or tipe = 'PENDAPATAN DILUAR USAHA'", "AND")
            ->customWhere("tipe = 'BEBAN' or tipe = 'BEBAN DILUAR USAHA'", "OR")
            ->where("is_deleted", "=", 0)
            ->findAll();

    foreach ($models as $key => $value) {
        $value->nama = $value->nama;
        $spasi = ($value->level == 1) ? '' : str_repeat("--", $value->level - 1);
        $value->nama_lengkap = $spasi . $value->kode . ' - ' . $value->nama;
    }

    return successResponse($response, ['list' => $models]);
});

/**
 * Ambil akun saja tanpa klasifikasinya
 */
$app->get('/acc/m_akun/akunDetail', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $db->select("*")
            ->from("acc_m_akun")
            ->where("is_tipe", "=", 0)
            ->andWhere("is_deleted", "=", 0);
    if (isset($params['nama'])) {
        $db->andWhere("nama", "like", $params['nama']);
    }
    $models = $db->findAll();

    foreach ($models as $key => $value) {
        $value->nama = $value->nama;
    }
    return successResponse($response, ['list' => $models]);
});
/**
 * Ambil tanggal setting
 */
$app->get('/acc/m_akun/getTanggalSetting', function ($request, $response) {
    $db = $this->db;
    $models = getMasterSetting();
    $models->tanggal = date('Y-m-d H:i:s', strtotime($models->tanggal));
    return successResponse($response, $models);
});
/**
 * Ambil akun by id
 */
$app->get('/acc/m_akun/getakun/{id}', function ($request, $response) {
    $id = $request->getAttribute('id');
    $db = $this->db;
    $data = $db->select("kode")
            ->from("acc_m_akun")
            ->where('id', '=', $id)
            ->find();
    return successResponse($response, ['data' => $data]);
});
/*
 * pengecualian akun untuk neraca
 */
$app->get("/acc/m_akun/getPengecualian", function ($request, $response) {
    $data = getMasterSetting();
    return successResponse($response, $data);
});
$app->post("/acc/m_akun/savePengecualian", function ($request, $response) {
    $db = $this->db;
    $params = $request->getParams();
    if ($params['type'] == "neraca") {
        $data["pengecualian_neraca"] = json_encode($params['data']);
    }
    if ($params['type'] == "labarugi") {
        $data["pengecualian_labarugi"] = json_encode($params['data']);
    }
    try {
        $models = $db->update('acc_m_setting', $data, ["id" => 1]);
        return successResponse($response, []);
    } catch (Exception $e) {
        return unprocessResponse($response, ["Terjadi Kesalahan pada server"]);
    }
});
