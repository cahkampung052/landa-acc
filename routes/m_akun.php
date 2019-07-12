<?php
/**
 * validasi akun
 */
function validasi($data, $custom = array())
{
    $validasi = array(
        'parent_id' => 'required',
        'kode'      => 'required',
        'nama'      => 'required',
        'is_kas' => 'required',
    );
    GUMP::set_field_name("parent_id", "Akun");
    GUMP::set_field_name("is_kas", "Kas");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}
/**
 * validasi saldo awal
 */
function validasiSaldo($data, $custom = array())
{
    $validasi = array(
        'tanggal'       => 'required',
        'm_lokasi_id'   => 'required',
    );
    GUMP::set_field_name("m_lokasi_id", "Lokasi");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}
/**
 * setLevelTipeAkun
 */
function setLevelTipeAkun($parent_id)
{
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $parent = $db->find("select * from acc_m_akun where id = '" . $parent_id . "'");
    return $parent->level + 1;
}

/**
 * Ambil saldo awal
 */
$app->get('/acc/m_akun/getSaldoAwal', function ($request, $response) {
    $params  = $request->getParams();
    $db = $this->db;
    $db->select("
        acc_m_akun.*,
        acc_trans_detail.debit,
        acc_trans_detail.kredit,
        acc_trans_detail.tanggal
    ")
        ->from('acc_m_akun')
        ->leftJoin(
            'acc_trans_detail',
            'acc_trans_detail.m_lokasi_id = ' . $params['m_lokasi_id'] . ' and 
            acc_trans_detail.m_akun_id = acc_m_akun.id and
            acc_trans_detail.reff_type = "Saldo Awal"'
        )
        ->where("acc_m_akun.is_deleted", "=", 0)
        ->orderBy('acc_m_akun.kode');
    $models = $db->findAll();
    /*
     * deklarasi tanggal untuk cek form.tanggal di index
     */
    $tanggal = $params['tanggal'];
    foreach ($models as $key => $value) {
        $spasi               = ($value->level == 1) ? '' : str_repeat("···", $value->level - 1);
        $value->nama_lengkap = $spasi . $value->kode . ' - ' . $value->nama;
        if (empty($value->kredit)) {
            $value->kredit = 0;
        }
        if (empty($value->debit)) {
            $value->debit = 0;
        }
        if (!empty($value->tanggal)) {
            $tanggal = $value->tanggal;
        }
    }
    return successResponse($response, ['detail' => $models, 'tanggal' => $tanggal]);
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
                        $detail['m_lokasi_id']  = $m_lokasi_id;
                        $detail['tanggal']    = $tanggal;
                        $detail['reff_type']  = 'Saldo Awal';
                        $detail['keterangan'] = 'Saldo Awal';
                        $detail['debit']      = !empty($val['debit']) ? $val['debit'] : 0;
                        $detail['kredit']     = !empty($val['kredit']) ? $val['kredit'] : 0;
                        $detail['m_akun_id']  = $val['id'];
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
    
    $path = 'file/format_saldo_awal.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);

    $objPHPExcel->getActiveSheet()->setCellValue('D' . 3, $tanggalsetting);
    
    $row = 4;
    foreach($lokasi as $key => $val){
        
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $row, $val->id);
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $row, $val->kode ." - ". $val->nama);
        $row++;
    }
    
    $objPHPExcel->getActiveSheet()->setCellValue('H' . 3, $lokasi[0]->id);
    $objPHPExcel->getActiveSheet()->setCellValue('H' . 4, $tanggalsetting);
    
    $rows = 6;
    foreach($akun as $key => $val){
        
        $spasi               = ($val->level == 1) ? '' : str_repeat("···", $val->level - 1);
        $val->nama_lengkap = $spasi . $val->kode . ' - ' . $val->nama;
        
        
        $objPHPExcel->getActiveSheet()->setCellValue('G' . $rows, $val->id);
        $objPHPExcel->getActiveSheet()->setCellValue('H' . $rows, $val->nama_lengkap);
        if($val->is_tipe == 0){
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
        $inputFileName = "file/" . DIRECTORY_SEPARATOR . $newName;
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
                $models[$row] = (array)$akun;
                $spasi               = ($akun->level == 1) ? '' : str_repeat("···", $akun->level - 1);
                $models[$row]['nama_lengkap'] = $spasi . $akun->kode . ' - ' . $akun->nama;
                $models[$row]['debit'] = $objPHPExcel->getSheet(0)->getCell('I' . $row)->getValue();
                $models[$row]['kredit'] = $objPHPExcel->getSheet(0)->getCell('J' . $row)->getValue();
            }
            
            unlink($inputFileName);
//            print_r($models);die();
            
            $data['lokasi'] = $db->select("*")->from("acc_m_lokasi")->where("id", "=", $objPHPExcel->getSheet(0)->getCell('H' . 3)->getValue())->find();
            $data['tanggal'] = $objPHPExcel->getSheet(0)->getCell('H' . 3)->getValue();
            return successResponse($response, ['data'=>$data, 'detail'=>$models]);
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
    /** Set limit */
    if (isset($params['limit']) && !empty($params['limit'])) {
        $db->limit($params['limit']);
    }
    /** Set offset */
    if (isset($params['offset']) && !empty($params['offset'])) {
        $db->offset($params['offset']);
    }
    $models    = $db->findAll();
    $totalItem = $db->count();
    foreach ($models as $key => $value) {
        $saldo               = getSaldo($value->id, null, null);
        $spasi               = ($value->level == 1) ? '' : str_repeat("···", $value->level - 1);
        $value->nama_lengkap = $spasi . $value->kode . ' - ' . $value->nama;
        $value->parent_id    = (int) $value->parent_id;
        $value->saldo_normal    = (int) $value->saldo_normal;
        $value->is_kas    = (int) $value->is_kas;
        $value->kode         = str_replace($value->kode_induk.".", "", $value->kode);
        $value->saldo        = $saldo;
        $value->tipe         = ($value->tipe == 'No Type') ? '' : $value->tipe;
    }
    return successResponse($response, ['list' => $models, 'totalItems'  => $totalItem]);
});
/**
 * Ambil list akun
 */
$app->get('/acc/m_akun/listakun', function ($request, $response) {
    $sql = $this->db;
    $data = $sql->findAll('select * from acc_m_akun where is_deleted = 0 order by kode');
    foreach ($data as $key => $val) {
        $data[$key] = (array) $val;
        $spasi = ($val->level == 1) ? '' : str_repeat("···", $val->level - 1);
        $data[$key]['nama_lengkap'] = $spasi . $val->kode . ' - ' . $val->nama;
    }
    return successResponse($response, $data);
});
/**
 * Simpan akun
 */
$app->post('/acc/m_akun/save', function ($request, $response) {
    $data   = $request->getParams();
    $sql    = $this->db;
    $data['tipe']      = isset($data['tipe']) ? $data['tipe'] : '';
    $data['parent_id'] = isset($data['parent_id']) ? $data['parent_id'] : '';
    $validasi = validasi($data);
    if ($validasi === true) {
        $data['is_tipe'] = 0;
        $data['kode']    = $data['parent_id'] == 0 ? $data['kode'] : $data['kode_induk'] . '.' . $data['kode'];
        if ($data['parent_id'] == 0) {
            $data['level'] = 1;
        } else {
            $data['level'] = setLevelTipeAkun($data['parent_id']);
            /**
             * Update tipe akun di atasnya
             */
            $akun         = $sql->find("select * from acc_m_akun where id = '" . $data['parent_id'] . "'");
            $data['tipe'] = isset($akun->tipe) ? $akun->tipe : '';
        }
        /**
         * Simpan ke database
         */
        if (isset($data['id']) && !empty($data['id'])) {
            $model = $sql->update("acc_m_akun", $data, ["id" => $data["id"]]);
        } else {
            $model = $sql->insert("acc_m_akun", $data);
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
    $db   = $this->db;
    $update['is_deleted']   = $data['is_deleted'];
    $update['tgl_nonaktif'] = date('Y-m-d');
    $model = $db->update("acc_m_akun", $update, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
/**
 * Import akun
 */
$app->post('/acc/m_akun/import', function ($request, $response) {
    $db = $this->db;
    if (!empty($_FILES)) {
        $tempPath = $_FILES['file']['tmp_name'];
        $newName  = urlParsing($_FILES['file']['name']);
        $inputFileName = "./upload" . DIRECTORY_SEPARATOR . rand() . "_" . $newName;
        move_uploaded_file($tempPath, $inputFileName);
        if (file_exists($inputFileName)) {
            try {
                $inputFileType = PHPExcel_IOFactory::identify($inputFileName);
                $objReader     = PHPExcel_IOFactory::createReader($inputFileType);
                $objPHPExcel   = $objReader->load($inputFileName);
            } catch (Exception $e) {
                die('Error loading file "' . pathinfo($inputFileName, PATHINFO_BASENAME) . '": ' . $e->getMessage());
            }
            $sheet         = $objPHPExcel->getSheet(0);
            $highestRow    = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $id_parent     = 0;
            $level         = 0;
            $tipe          = '';
            for ($row = 2; $row <= $highestRow; $row++) {
                $kode = $objPHPExcel->getSheet(0)->getCell('A' . $row)->getValue();
                if (isset($kode)) {
                    $cek_tipe_akun = $db->find("select * from acc_m_akun where is_tipe=1 and kode='{$kode}'");
                    if (isset($cek_tipe_akun->id)) {
                        $id_parent = $cek_tipe_akun->id;
                        $level     = $cek_tipe_akun->level;
                        $tipe      = $cek_tipe_akun->tipe;
                    } else {
                        $data['kode']       = $kode;
                        $data['nama']       = $objPHPExcel->getSheet(0)->getCell('B' . $row)->getValue();
                        $data['is_tipe']    = 0;
                        $data['tipe']       = $tipe;
                        $data['level']      = $level + 1;
                        $data['parent_id']  = $id_parent;
                        $data['is_deleted'] = 0;
                        $insert = $db->insert("acc_m_akun", $data);
                    }
                }
            }
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
    $model = $db->findAll("select * from acc_m_akun where is_deleted = 0 and is_tipe=1 order by kode asc");
    $data_model = [];
    $data_kode  = [];
    foreach ($model as $key => $val) {
        $data_model[$key]         = (array) $val;
        $data_model[$key]['kode'] = $val->kode;
        $data_model[$key]['nama'] = $val->nama;
        $data_model[$key]['tipe'] = $val->tipe;
        $data_kode[]              = $val->kode;
    }
    $data_contoh = [];
    foreach ($data_kode as $key => $val) {
        $data_contoh[$key]['kode'] = $val . "-01";
        $data_contoh[$key]['nama'] = "nama akun turunan";
        $data_contoh[$key]['tipe'] = "";
    }
    $array_gabung = array_merge($data_model, $data_contoh);
    //Shorting
    $kode_short = [];
    foreach ($array_gabung as $val) {
        $kode_short[] = $val['kode'];
    }
    array_multisort($kode_short, SORT_ASC, $array_gabung);
    //load themeplate
    $path        = 'acc/landaacc/upload/format_akun.xls';
    $objReader   = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($path);
    $row = 1;
    foreach ($array_gabung as $key => $val) {
        $row++;
        if (isset($val['id'])) {
            $objPHPExcel->getActiveSheet()->getStyle('A' . $row . ':C' . $row)->applyFromArray(
                [
                    'alignment' => [
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
                    ],
                    'font'      => [
                        'bold' => true,
                    ],
                    'borders'   => [
                        'allborders' => [
                            'style' => PHPExcel_Style_Border::BORDER_THIN,
                        ],
                    ],
                ]
            );
        } else {
            $objPHPExcel->getActiveSheet()->getStyle('A' . $row . ':C' . $row)->applyFromArray(
                [
                    'alignment' => [
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
                    ],
                    'borders'   => [
                        'allborders' => [
                            'style' => PHPExcel_Style_Border::BORDER_THIN,
                        ],
                    ],
                ]
            );
            $objPHPExcel->getActiveSheet()->mergeCells('B' . $row . ':C' . $row);
        }
        $objPHPExcel->getActiveSheet()
            ->setCellValue('A' . $row, $val['kode'])
            ->setCellValue('B' . $row, $val['nama'])
            ->setCellValue('C' . $row, $val['tipe'])
            ->getRowDimension($row)
            ->setRowHeight(20);
    }
    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=format_akun.xls");
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});
/**
 * Ambil budget
 */
$app->get('/acc/m_akun/getBudget', function ($request, $response) {
    $params = $request->getParams();
    $db    = $this->db;
    $getBudget = $db->select("*")
      ->from("acc_budgeting")
      ->where("tahun", "=", $params['tahun'])
      ->andWhere("m_akun_id", "=", $params['m_akun_id'])
      ->findAll();
    $list=[];
    foreach ($getBudget as $key => $value) {
        $list[$value->bulan] = (array)$value;
    }
    $listBudget=[];
    for ($i=1; $i<=12; $i++) {
        $listBudget[$i]['id']         = isset($list[$i]) ? $list[$i]['id'] : null;
        $listBudget[$i]['budget']   = isset($list[$i]) ? $list[$i]['budget'] : 0;
        $listBudget[$i]['nama_bulan'] = date('F', mktime(0, 0, 0, $i, 10)); // March
    }
    return successResponse($response, $listBudget);
});
/**
 * Simpan budget
 */
$app->post('/acc/m_akun/saveBudget', function ($request, $response) {
    $params = $request->getParams();
    $db    = $this->db;
    try {
        foreach ($params['listBudget'] as $key => $value) {
            $data = [
          'm_akun_id'   => $params['form']['id'],
          'bulan'       => date('m', strtotime($value['nama_bulan'])),
          'tahun'       => $params['form']['tahun'],
          'budget'    => $value['budget']
        ];
            if (isset($value['id'])) {
                $db->update('acc_budgeting', $data, ['id' => $value['id']]);
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
    $models = $db->select("*")->from("acc_m_akun")
            ->where("is_kas", "=", 1)
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0)
            ->findAll();
    return successResponse($response, ['list' => $models]);
});
/**
 * Ambil akun saja tanpa klasifikasinya
 */
$app->get('/acc/m_akun/akunDetail', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0)
            ->findAll();
    return successResponse($response, ['list' => $models]);
});
/**
 * Ambil tanggal setting
 */
$app->get('/acc/m_akun/getTanggalSetting', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")
            ->from("acc_m_setting")
            ->orderBy("id DESC")
            ->find();
    $models->tanggal = date('Y-m-d H:i:s', strtotime($models->tanggal));
    return successResponse($response, $models);
});
/**
 * Ambil akun by id
 */
$app->get('/acc/m_akun/getakun/{id}', function ($request, $response) {
    $id   = $request->getAttribute('id');
    $db   = $this->db;
    $data = $db->select("kode")
        ->from("acc_m_akun")
        ->where('id', '=', $id)
        ->find();
    return successResponse($response, ['data' => $data]);
});
