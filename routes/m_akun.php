<?php

function validasi($data, $custom = array())
{
    $validasi = array(
        'parent_id' => 'required',
        'kode'      => 'required',
        'nama'      => 'required',
        // 'tipe' => 'required',
    );
    GUMP::set_field_name("parent_id", "Akun");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

function validasiSaldo($data, $custom = array())
{
    $validasi = array(
        'tanggal' => 'required',
        'm_lokasi_id'      => 'required',
        // 'tipe' => 'required',
    );
    GUMP::set_field_name("m_lokasi_id", "Lokasi");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->post('/acc/m_akun/saveSaldoAwal', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params['form']);die();
    $validasi = validasiSaldo($params['form']);
//    print_r($validasi);die();
    if ($validasi === true) {
        $tanggal   = date("Y-m-d", strtotime($params['form']['tanggal']));
        $m_lokasi_id = $params['form']['m_lokasi_id'];

        if (!empty($params['detail'])) {
            $db = $this->db;

            /**
             * Delete saldo awal di trans detail
             */
            $delete = $db->delete('acc_trans_detail', ['m_lokasi_id' => $m_lokasi_id, 'keterangan' => 'Saldo Awal', 'reff_type' => 'Saldo Awal']);

            /**
             * Masukkan saldo awal
             */
            if($delete){
                foreach ($params['detail'] as $val) {
                if ((isset($val['debit']) && !empty($val['debit'])) || (isset($val['kredit']) && !empty($val['kredit']))) {
                    $detail['m_lokasi_id']  = $m_lokasi_id;
                    $detail['tanggal']    = $tanggal;
                    $detail['reff_type']       = 'Saldo Awal';
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
    }else{
        return unprocessResponse($response, $validasi);
    }

    
});

$app->get('/acc/m_akun/getSaldoAwal', function ($request, $response) {
    $params  = $request->getParams();
    $tanggal = date("Y-m-d");
    $db = $this->db;
    $db->select("
        acc_m_akun.*,
        acc_trans_detail.debit,
        acc_trans_detail.kredit,
        acc_trans_detail.tanggal
    ")
        ->from('acc_m_akun')
        ->leftJoin('acc_trans_detail', 
            'acc_trans_detail.m_lokasi_id = ' . $params['m_lokasi_id'] . ' and 
            acc_trans_detail.m_akun_id = acc_m_akun.id and
            acc_trans_detail.reff_type = "Saldo Awal" and
            acc_trans_detail.keterangan = "Saldo Awal"')
        ->where("acc_m_akun.is_deleted", "=", 0)
//            ->where("acc_trans_detail.m_lokasi_id", "=", $params['m_lokasi_id'])
            ->orderBy('acc_m_akun.kode');

    $models = $db->findAll();
    
    foreach ($models as $key => $value) {
        $spasi               = ($value->level == 1) ? '' : str_repeat("···", $value->level - 1);
        $value->nama_lengkap = $spasi . $value->kode . ' - ' . $value->nama;

//        if (!empty($value->m_unker_id)) { $tanggal = $value->tanggal; }
        if (empty($value->kredit)) { $value->kredit = 0; }
        if (empty($value->debit)) { $value->debit = 0; }
    }   
//    print_r($models);die();
    return successResponse($response, ['detail' => $models, 'tanggal' => $tanggal]);
});

$app->get('/acc/m_akun/index', function ($request, $response) {
    $params = $request->getParams();
    // $sort     = "acc_akun.kode ASC";
    $offset   = isset($params['offset']) ? $params['offset'] : 0;
    $limit    = isset($params['limit']) ? $params['limit'] : 20;
    $idcabang = isset($params['params_cabang']) ? $params['params_cabang'] : '';

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
            }else if ($key == 'kode') {
                $db->where("acc_m_akun.kode", 'LIKE', $val);
            }else if ($key == 'nama') {
                $db->where("acc_m_akun.nama", 'LIKE', $val);
            }else{
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
    // print_r($models);exit();
    $arr = array();
    foreach ($models as $key => $value) {
        $arr[$key] = (array) $value;

        $spasi                     = ($value->level == 1) ? '' : str_repeat("···", $value->level - 1);
        $arr[$key]['nama_lengkap'] = $spasi . $value->kode . ' - ' . $value->nama;
        $arr[$key]['parent_id']    = $value->parent_id;
        $arr[$key]['kode']         = str_replace($value->kode_induk.".", "", $value->kode);
        $arr[$key]['is_kasir']     = $value->is_kasir == 1 ? true : false;
        // $arr[$key]['kode_akun']    = str_replace($value->kode_induk.".", "", $value->kode);

        if ($value->tipe == 'Hutang Lancar' || $value->tipe == 'Hutang Tidak Lancar') {
            $arr[$key]['tipe'] = 'Hutang';
        } else if ($value->tipe == 'Piutang Usaha' || $value->tipe == 'Piutang Lain') {
            $arr[$key]['tipe'] = 'Piutang';
        } else if ($value->tipe == 'No Type') {
            $arr[$key]['tipe'] = '';
        }

        if ($value->is_tipe == 0) {
//            $saldo              = saldo($value->id, date("Y-m-d"), $idcabang);
//            $arr[$key]['saldo'] = (!empty($saldo)) ? rp($saldo) : 0;
        }
    }
//      print_r($arr);exit();
    return successResponse($response, [
      'list'        => $arr,
      'totalItems'  => $totalItem,
      'base_url'    => str_replace('api/', '', config('SITE_URL'))
    ]);
});

$app->get('/acc/m_akun/cabang', function ($request, $response) {
    $params = $request->getParams();
    $db     = $this->db;
    $data   = $db->select("*")
        ->from('m_unker')
        ->where("is_deleted", "=", '0')
        ->findAll();

    return successResponse($response, ['data' => $data]);
});

function setLevelTipeAkun($parent_id)
{
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $parent = $db->find("select * from acc_m_akun where id = '" . $parent_id . "'");
    return $parent->level + 1;
}

$app->post('/acc/m_akun/create', function ($request, $response) {

    $params = $request->getParams();
    $data   = $params;
    $sql    = $this->db;

    $data['tipe']      = isset($data['tipe']) ? $data['tipe'] : '';
    $data['parent_id'] = isset($data['parent_id']) ? $data['parent_id'] : '';

    $validasi = validasi($data);
    if ($validasi === true) {

        $data['is_tipe'] = 0;
        $data['kode']    = $data['kode_induk'] . '.' . $data['kode'];

        if ($data['parent_id'] == 0) {
            $data['level'] = 1;
        } else {
            $data['level'] = setLevelTipeAkun($data['parent_id']);

            $akun         = $sql->find("select * from acc_m_akun where id = '" . $data['parent_id'] . "'");
            $data['tipe'] = isset($akun->tipe) ? $akun->tipe : '';
        }

        $model = $sql->insert("acc_m_akun", $data);
        if ($model) {
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/m_akun/cariunker', function ($request, $response) {
    $params = $request->getParams();
    $sql    = $this->db;
    $id     = $request->getAttribute('id');
    $data   = $sql->findAll("select * from acc_m_akun where (is_tipe=0 and is_deleted = 0 and nama like '%{$params['nama']}%') order by kode");
    foreach ($data as $key => $val) {
        $data[$key]                 = (array) $val;
        $spasi                      = ($val->level == 1) ? '' : str_repeat("···", $val->level - 1);
        $data[$key]['nama_lengkap'] = $spasi . $val->kode . ' - ' . $val->nama;
    }
    return successResponse($response, $data);
});

$app->get('/acc/m_akun/selecttype/{type}/{m_unker_id}', function ($request, $response) {
    $sql = $this->db;

//    $id_unker = [];
    //    foreach ($_SESSION['user']['cabang'] as $key => $val) {
    //        $id_unker[] = $val['id'];
    //    }
    //    $rapikan = implode(",", $id_unker);
    $type      = $request->getAttribute('type');
    $m_unker_id = $request->getAttribute('m_unker_id');

    if ($type == 6) {
      // $stype = "Receivable";
      $models = $sql->findAll("SELECT * FROM acc_m_akun WHERE is_tipe = 0 AND is_deleted = '0' AND tipe = 'Piutang Usaha' OR tipe = 'Piutang Lain'");

    } else if ($type == 9) {
      // $stype = "Payable";
      $models = $sql->findAll("SELECT * FROM acc_m_akun WHERE is_tipe = 0 AND is_deleted = '0' AND tipe = 'Hutang Lancar' OR tipe = 'Hutang Tidak Lancar'");

    } else {
      $stype = "Cash & Bank";
      $models = $sql->findAll("SELECT * FROM acc_m_akun WHERE is_tipe = 0 AND is_deleted = '0' AND tipe = '$stype'");
    }

    $details = [];
    foreach ($models as $key => $val) {
        $details[$key] = $val;
    }

    return successResponse($response, ['data' => $details, "type" => $type]);
});

$app->get('/acc/m_akun/selecttypes/{type}/{unker_id}/{mdl}', function ($request, $response) {
    $sql      = $this->db;
    $type     = $request->getAttribute('type');
    $unker_id = $request->getAttribute('unker_id');
    $mdl      = $request->getAttribute('mdl');

    $sql->select("*")->from("acc_m_akun")
        ->where("is_deleted", "=", 0)
        ->andWhere("is_tipe", "=", 0);

    // if ($stype != "All") {
    //     $sql->where("tipe", "=", $stype);
    // } else

    if ($mdl == "msk") {
        if ($type == 4) {
          // $stype = "Pendapatan";
          $sql->where("tipe", "=", "Pendapatan");

        } else if ($type == 5) {
          // $stype = "Receivable";
          $sql->customWhere("tipe IN ('Piutang Usaha', 'Piutang Lain')", "AND");

        } else {
            $stype = "All";
        }
    } else if ($mdl == "klr") {
        if ($type == 7) {
          // $stype = "Biaya";
          $sql->where("tipe", "=", "Biaya");

        } elseif ($type == 8) {
          // $stype = "Payable";
          $sql->customWhere("tipe IN ('Hutang Lancar', 'Hutang Tidak Lancar')", "AND");

        } else {
            $stype = "All";
        }
    }

    if ($stype == "All"){
        if ($mdl == "klr") {
            $sql->customWhere("tipe in ('Biaya','Cash & Bank','Modal')", "AND");
        } elseif ($mdl == 'msk') {
            $sql->customWhere("tipe in ('Pendapatan','Cash & Bank','Modal')", "AND");
        }
    }

    $models  = $sql->findAll();
    $details = [];
    foreach ($models as $key => $val) {
        $details[$key] = $val;
    }

    return successResponse($response, ['data' => $details, "type" => $type]);
});

$app->get('/acc/m_akun/getAll', function ($request, $response) {
    $params = $request->getParams();
    $sql    = $this->db;

    $models = $sql->findAll('select * from acc_m_akun where is_deleted = 0  and is_tipe = 0');

    return successResponse($response, ['data' => $models]);
});

$app->post('/acc/m_akun/caritype/{type}/cari', function ($request, $response) {
    $params = $request->getParams();
    $sql    = $this->db;

    $type = $request->getAttribute('type');
//    $unt_id = $request->getAttribute('unit_id');

    if ($type == 6) {
      // $stype = "'Receivable'";
      $stype = "'Piutang Usaha','Piutang Lain'";

    } else if ($type == 9) {
      // $stype = "'Payable'";
      $stype = "'Hutang Lancar','Hutang Tidak Lancar'";

    } else {
        $stype = "'Cash & Bank'";
    }

    $sql->select("*")
    ->from("acc_m_akun")
    ->customWhere("(is_deleted = 0 and tipe in ({$stype})) and (nama like '%{$params['nama']}%') and level >= 2 and is_tipe = 0");
//    $sql->log();
    $models = $sql->findAll();

    return successResponse($response, ['data' => $models, "type" => $type]);
});

$app->get('/acc/m_akun/istype/{type}', function ($request, $response) {
    $sql  = $this->db;
    $type = $request->getAttribute('type');
    if ($type == 'hutang') {
        // $tipe = 'and tipe = "Payable"';
        $tipe = "and tipe IN('Hutang Lancar', 'Hutang Tidak Lancar')";

    } else if ($type == 'kas') {
        $tipe = 'and tipe = "Cash & Bank"';

    } elseif ($type == 'piutang') {
        // $tipe = 'and tipe = "Receivable"';
        $tipe = "and tipe IN('Piutang Usaha', 'Piutang Lain')";

    } else{
        $tipe = 'and tipe LIKE "%'.str_replace('-', ' ', str_replace('_', ' ', $type)).'%"';
    }
    $data = $sql->findAll('select * from acc_m_akun where is_deleted = 0 and is_tipe = 0 ' . $tipe . ' order by kode');
    return successResponse($response, $data);
});

$app->get('/acc/m_akun/listakun', function ($request, $response) {
    $sql  = $this->db;
    $data = $sql->findAll('select * from acc_m_akun where is_deleted = 0 order by kode');

    foreach ($data as $key => $val) {
        $data[$key]                 = (array) $val;
        $spasi                      = ($val->level == 1) ? '' : str_repeat("···", $val->level - 1);
        $data[$key]['nama_lengkap'] = $spasi . $val->kode . ' - ' . $val->nama;
    }
    return successResponse($response, $data);
});

$app->get('/acc/m_akun/akununker/{id}', function ($request, $response) {
    $sql  = $this->db;
    $id   = $request->getAttribute('id');
    $data = $sql->findAll('select * from acc_m_akun where is_deleted = 0 and (m_unker_id = "' . $id . '" OR is_tipe=1) order by kode');

    foreach ($data as $key => $val) {
        $data[$key]                 = (array) $val;
        $spasi                      = ($val->level == 1) ? '' : str_repeat("···", $val->level - 1);
        $data[$key]['nama_lengkap'] = $spasi . $val->kode . ' - ' . $val->nama;
    }
    return successResponse($response, $data);
});

$app->get('/acc/m_akun/getakun/{id}', function ($request, $response) {
    $db   = $this->db;
    $id   = $request->getAttribute('id');
    $data = $db->select("kode")
        ->from("acc_m_akun")
        ->where('id', '=', $id)
        ->find();
    return successResponse($response, ['data' => $data]);
});

$app->get('/acc/m_akun/getakunkas', function ($request, $response) {
    $db   = $this->db;
    $data = $db->select('*')
        ->from('acc_m_akun')
        ->where('tipe', '=', 'Cash & Bank')
        ->andWhere('is_deleted', '=', 0)
        ->andWhere('level', '=', 2)
        ->findAll();
    return successResponse($response, $data);
});

$app->get('/acc/m_akun/getakunbytipe', function ($request, $response) {
    $params = $request->getParams();

    $db   = $this->db;
    $data = $db->select('*')
        ->from('acc_m_akun')
        // ->where('tipe', '=', $params['tipe'])
        ->customWhere("tipe LIKE '%{$params['tipe']}%'")
        ->andWhere('is_deleted', '=', 0)
        ->andWhere('level', '>', 2)
        ->findAll();
    return successResponse($response, $data);
});

$app->get('/acc/m_akun/list/{type}/{m_unker_id}', function ($request, $response) {
    $sql       = $this->db;
    $m_unker_id = $request->getAttribute('m_unker_id');
    $type      = $request->getAttribute('type');

    if ($type == 'hutang') {
      // $tipe = 'and tipe = "Payable"';
      $tipe = "and tipe IN('Hutang Lancar', 'Hutang Tidak Lancar')";

    } else if ($type == 'kas') {
      $tipe = 'and tipe = "Cash & Bank"';

    } elseif ($type == 'piutang') {
      // $tipe = 'and tipe = "Receivable"';
      $tipe = "and tipe IN('Piutang Usaha', 'Piutang Lain')";

    }

    $data = $sql->findAll('SELECT * from acc_m_akun where is_deleted = 0 and is_tipe = 0 ' . $tipe . ' and m_unker_id =' . $m_unker_id . ' order by kode');
    return successResponse($response, $data);
});
$app->get('/acc/m_akun/saldoakunkas/{m_unker_id}', function ($request, $response) {
    $m_unker_id = $request->getAttribute('m_unker_id');
    $sql       = $this->db;
    $data      = $sql->findAll("select * from acc_m_akun where is_deleted = 0 and is_tipe = 0 and tipe = 'Cash & Bank' order by kode");
    $cabang    = $sql->find("select * from m_unker where is_deleted = 0 order by id asc");
    $cbg_id    = ($m_unker_id != 0) ? $m_unker_id : $cabang->id;
    $starttime = new DateTime("NOW");
    $starttime->setTimezone(new DateTimeZone("Asia/Jakarta"));
    $tanggal_start = $starttime->format("Y-m-d");
    $return        = [];
    foreach ($data as $key => $val) {
        $saldo                 = saldo($val->id, '', '');
        $return[$key]          = (array) $val;
        $return[$key]['saldo'] = (!empty($saldo)) ? rp($saldo) : 0;
    }
    return successResponse($response, ['data' => $return, 'cabangid' => $cbg_id]);
});

$app->post('/acc/m_akun/update', function ($request, $response) {

    $data = $request->getParams();
    $db   = $this->db;

    // $data['parent_id'] = isset($data['parent_id']) ? $data['parent_id'] : '';

    $validasi = validasi($data);

    if ($validasi === true) {

        // $data['kode'] = $data['kode'];

        // if ($data['parent_id'] == 0) {
        //     $data['level'] = 1;
        // } else {
        //     $data['level'] = setLevelTipeAkun($data['parent_id']);
        // }

        $akun         = $db->find("select * from acc_m_akun where id = '" . $data['parent_id'] . "'");
        $data['tipe'] = isset($akun->tipe) ? $akun->tipe : '';
        $data['kode']    = $data['kode_induk'] . '.' . $data['kode'];

        $model = $db->update("acc_m_akun", $data, array('id' => $data['id']));
        if ($model) {
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/m_akun/trash', function ($request, $response) {

    $data = $request->getParams();
    $db   = $this->db;

//    $cek_komponenGaji = $db->select('*')
//    ->from('m_komponen_gaji')
//    ->where('m_akun_id','=',$data['id'])
//    ->find();
//
//    if (!empty($cek_komponenGaji)) {
//       return unprocessResponse($response, ['Data Akun Masih Di Gunakan Pada Master Komponen Gaji']);
//    }

//    $cek_Gaji = $db->select('*')
//    ->from('t_penggajian')
//    ->where('m_akun_id','=',$data['id'])
//    ->find();
//
//    if (!empty($cek_Gaji)) {
//       return unprocessResponse($response, ['Data Akun Masih Di Gunakan Pada Transaksi Penggajian']);
//    }

    $model = $db->update("acc_m_akun", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->post('/acc/m_akun/delete', function ($request, $response) {
    $data = $request->getParams();
    $db   = $this->db;

//    $cek = $db->select("*")
//    ->from("acc_trans_detail")
//    ->where("m_akun_id", "=", $request->getAttribute('id'))
//    ->find();
//
//    if ($cek) {
//        return unprocessResponse($response, ['Data Akun Masih Di Gunakan Pada Transaksi']);
//    }
//
//    $cek_komponenGaji = $db->select('*')
//    ->from('m_komponen_gaji')
//    ->where('m_akun_id','=',$data['id'])
//    ->find();
//
//    if (!empty($cek_komponenGaji)) {
//       return unprocessResponse($response, ['Data Akun Masih Di Gunakan Pada Master Komponen Gaji']);
//    }
//
//    $cek_Gaji = $db->select('*')
//    ->from('t_penggajian')
//    ->where('m_akun_id','=',$data['id'])
//    ->find();
//
//    if (!empty($cek_Gaji)) {
//       return unprocessResponse($response, ['Data Akun Masih Di Gunakan Pada Transaksi Penggajian']);
//    }

    $delete = $db->delete('acc_m_akun', array('id' => $data['id']));
       if ($delete) {
           return successResponse($response, ['data berhasil dihapus']);
       } else {
           return unprocessResponse($response, ['data gagal dihapus']);
       }
});

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
//            echo json_encode($tes);
            //            exit();
            unlink($inputFileName);

            return successResponse($response, 'data berhasil di import');
        } else {
            return unprocessResponse($response, 'data gagal di import');
        }
    }
});

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

$app->get('/acc/m_akun/getBudget', function ($request, $response) {
    $params = $request->getParams();
    $db    = $this->db;
//    print_r($params);die();
    $getBudget = $db->select("*")
      ->from("acc_budgeting")
      ->where("tahun", "=", $params['tahun'])
      ->andWhere("m_akun_id", "=", $params['m_akun_id'])
      ->findAll();
    $list=[];
    foreach ($getBudget as $key => $value) {
      $list[$value->bulan] = (array)$value;
    }
//print_r($getBudget);die();
    $listBudget=[];
    for($i=1; $i<=12; $i++){
        $listBudget[$i]['id']         = isset($list[$i]) ? $list[$i]['id'] : NULL;
        $listBudget[$i]['budget']   = isset($list[$i]) ? $list[$i]['budget'] : 0;
        $listBudget[$i]['nama_bulan'] = date('F', mktime(0, 0, 0, $i, 10)); // March
    }
//    print_r($listBudget);die();
    return successResponse($response, $listBudget);
});

$app->post('/acc/m_akun/saveBudget', function ($request, $response) {
    $params = $request->getParams();
    $db    = $this->db;
//    print_r($params);die();
    try{
      foreach ($params['listBudget'] as $key => $value) {
        $data = [
          'm_akun_id'   => $params['form']['id'],
          'bulan'       => date('m', strtotime($value['nama_bulan'])),
          'tahun'       => $params['form']['tahun'],
          'budget'    => $value['budget']
        ];
        if( isset($value['id']) ){
          $db->update('acc_budgeting', $data, ['id' => $value['id']]);
        } else {
          $db->insert('acc_budgeting', $data);
        }
      }

      return successResponse($response, []);
    } catch(Exception $e) {

      return unprocessResponse($response, $e);
    }
});
$app->post('/acc/m_akun/save_akun_kasir', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;

    $datas['is_kasir'] = isset($data['is_kasir']) && $data['is_kasir'] == true ? 1 : 0;
    try {
        // print_r($datas);exit();
        $model = $db->update("acc_m_akun", $datas, array('id' => $data['id']));
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ['Data Gagal Di Simpan']);
    }
});

$app->get('/acc/m_akun/akunAll', function ($request, $response){
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->where("is_deleted", "=", 0)
            ->findAll();
    return successResponse($response, [
      'list'        => $models
    ]);
});

$app->get('/acc/m_akun/akunKas', function ($request, $response){
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->where("tipe", "=", "Cash & Bank")
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0)
            ->findAll();
    
    
    return successResponse($response, [
      'list'        => $models
    ]);
});

$app->get('/acc/m_akun/akunDetail', function ($request, $response){
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0)
            ->findAll();
    return successResponse($response, [
      'list'        => $models
    ]);
});

$app->get('/acc/m_akun/akunHutang', function ($request, $response){
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->customWhere("tipe IN('Hutang Lancar', 'Hutang Tidak Lancar')")
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0)
            
            ->findAll();
    return successResponse($response, [
      'list'        => $models
    ]);
});

$app->get('/acc/m_akun/akunPiutang', function ($request, $response){
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->customWhere("tipe IN('Piutang Usaha', 'Piutang Lain')")
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0)
            
            ->findAll();
    return successResponse($response, [
      'list'        => $models
    ]);
});

$app->get('/acc/m_akun/getTanggalSetting', function ($request, $response){
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_setting")
//            ->customWhere("tipe IN('Piutang Usaha', 'Piutang Lain')")
//            ->where("is_tipe", "=", 0)
//            ->where("is_deleted", "=", 0)
            ->orderBy("id DESC")
            
            ->find();
    $models->tanggal = date('Y-m-d H:i:s', strtotime($models->tanggal . ' -1 day'));
    return successResponse($response, $models);
});
