<?php
/**
 * Validasi
 * @param  array $data
 * @param  array $custom
 * @return array
 */
function validasi($data, $custom = array())
{
    $validasi = array(
        "kode" => "required",
        "nama" => "required",
        "tipe" => "required"
    );
    $cek = validate($data, $validasi, $custom);
    return $cek;
}
/**
 * Ambil semua hak akses
 */
$app->get('/acc/m_klasifikasi/index', function ($request, $response) {
    $params = $request->getParams();


    $filter = array();
    $sort = "acc_m_akun.kode ASC";
    $offset = 0;
    $limit = 1000;

    if (isset($params['limit'])) {
        $limit = $params['limit'];
    }

    if (isset($params['offset'])) {
        $offset = $params['offset'];
    }

    $db = $this->db;
    $db->select("acc_m_akun.*")
        ->from('acc_m_akun')
        ->limit($limit)
        ->orderBy($sort)
        ->offset($offset)
        ->where('is_tipe', '=', '1')
        ->orderBy('acc_m_akun.kode ASC');



    // $db->orderBy('kode_induk');


         // /** set parameter */
    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
          if ($key == 'is_deleted') {
            $db->where('acc_m_akun.is_deleted', '=', $val);
          } elseif ($key=="nama") {
              $db->where('acc_m_akun.nama', 'LIKE', $val);
          }elseif ($key=="kode") {
            $db->where('acc_m_akun.kode', 'LIKE', $val);
          }
        }
    }
    $models    = $db->findAll();
    $totalItem = $db->count();


    $arr = array();
    foreach ($models as $key => $value) {
        $arr[$key] = (array) $value;

        $spasi                            = ($value->level == 1) ? '' : str_repeat("···", $value->level - 1);
        $arr[$key]['nama_lengkap']        = $spasi . $value->kode . ' - ' . $value->nama;
        $arr[$key]['parent_id']           = $arr[$key]['parent_id'] == 0 ? (string) $value->parent_id : (int) $value->parent_id;
        // $arr[$key]['kode']             = kodeAkun($value->kode);
        $arr[$key]['kode']                = $value->kode;
//        $arr[$key]['is_kasir']            = $value->is_kasir == 1 ? true : false;
        // $arr[$key]['is_pendapatan_usaha'] = (string) $value->is_pendapatan_usaha;
        //
        // if ($value->tipe == 'Payable' || $value->tipe == 'Hutang Lancar' || $value->tipe == 'Hutang Tidak Lancar') {
        //     $arr[$key]['tipe'] = 'Hutang';
        //
        // } else if ($value->tipe == 'Receivable' || $value->tipe == 'Piutang Usaha' || $value->tipe == 'Piutang Lain' ) {
        //     $arr[$key]['tipe'] = 'Piutang';
        //
        // } else if ($value->tipe == 'No Type') {
        //     $arr[$key]['tipe'] = '';
        // }

        if ($value->tipe == 'No Type') {
          $arr[$key]['tipe'] = '';
        }

        if ($value->is_tipe == 0) {
            // $saldo              = saldo($value->id, null, $idcabang);
            $arr[$key]['saldo'] = (!empty($saldo)) ? rp($saldo) : 0;
        }
    }
    return successResponse($response, ['list' => $arr, 'totalItems' => $totalItem]);

    // foreach ($models as $key => $value) {
    //     $value->parent_id= $db->find('select * from m_so where id = '.$value->parent_id);
    // }


    // return successResponse($response, ['list' => $models, 'totalItems' => $totalItem]);
});

$app->get('/acc/m_klasifikasi/list', function ($request, $response){
    $db = $this->db;
    $models = $db->select("acc_m_akun.*")
        ->from('acc_m_akun')
        ->where('is_tipe', '=', '1')
        ->where('is_deleted', '=', 0)
        ->orderBy('acc_m_akun.kode ASC')
        ->findAll();
    return successResponse($response, ['list' => $models]);
});

$app->post('/acc/m_klasifikasi/create', function ($request, $response) {

    $data = $request->getParams();
//    print_r($data);die();

    $db = $this->db;
    $data['tipe'] = isset($data['tipe']) ? $data['tipe'] : '';

    $validasi = validasi($data);

    if ($validasi === true) {
        $data['is_tipe'] = 1;
        $data['kode'] = $data['parent_id'] == 0 ? $data['kode'] : $data['kode_induk'] . '.' . $data['kode'];

        if ($data['parent_id'] == 0) {
        $data['level'] = 1;
        } else {
            $data['level'] = setLevelTipeAkun($data['parent_id']);
        }

        $model = $db->insert("acc_m_akun", $data);
        if ($model) {
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/m_klasifikasi/update', function ($request, $response) {

    $data = $request->getParams();
    $db = $this->db;
    $validasi = validasi($data);
    if ($validasi === true) {
        $data['is_tipe'] = 1;
        // $data['kode'] = $data['parent_id'] == 0 ? $data['kode'] : $data['kode_induk'] . '.' . $data['kode'];

        // if (!$data['parent_id'] == 0) {
        // $data['level'] = 1;
        // } else {
        //     $data['level'] = setLevelTipeAkun($data['parent_id']);
        // }

        $model = $db->update("acc_m_akun", $data, array('id' => $data['id']));

        /** Update tipe di semua akun */
        $db->update('acc_m_akun', ['tipe' => $model->tipe], ['parent_id' => $model->id]);
        $db->update('acc_m_akun', ['tipe_arus' => $model->tipe_arus], ['parent_id' => $model->id]);

        if ($model) {
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }

    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/m_klasifikasi/trash', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    // print_r($data);exit;


    $datas['is_deleted'] = $data['is_deleted'];
    $datas['tgl_nonaktif'] = date('Y-m-d');
    try {
        $model = $db->update("acc_m_akun", $datas, array('id' => $data['id']));
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ['Data Gagal Di Simpan']);
    }
});

$app->post('/acc/m_klasifikasi/delete', function ($request, $response) {
    $data = $request->getParams();

    $db = $this->db;
    
    $cek = $db->select("*")->from("acc_m_akun")->where("parent_id", "=", $data['id'])->findAll();

    if (count($cek) > 0) {
        return unprocessResponse($response, ['Data Akun Masih Mempunyai Sub Akun']);
    } else {
        try {
            $delete = $db->delete('acc_m_akun', array('id' => $data['id']));
            return successResponse($response, ['data berhasil dihapus']);
        } catch (Exception $e) {
            return unprocessResponse($response, ['data gagal dihapus']);
        }
    }
});

/**
 * import
 */
$app->post('/acc/m_klasifikasi/import', function ($request, $response) {
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

            $sheet = $objPHPExcel->getSheet(0);
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            for ($row = 2; $row <= $highestRow; $row++) {
                $id = $objPHPExcel->getSheet(0)->getCell('A' . $row)->getValue();
                $kode = $objPHPExcel->getSheet(0)->getCell('B' . $row)->getValue();
                if (isset($kode) && isset($id)) {
                    $db->select("*")->from("m_akun")->where("id", "=", $id);
                    $data['id'] = $id;
                    $data['kode'] = $kode;
                    $data['nama'] = $objPHPExcel->getSheet(0)->getCell('C' . $row)->getValue();
                    $data['tipe'] = $objPHPExcel->getSheet(0)->getCell('D' . $row)->getValue();
                    $data['level'] = $objPHPExcel->getSheet(0)->getCell('E' . $row)->getValue();
                    $data['parent_id'] = $objPHPExcel->getSheet(0)->getCell('F' . $row)->getValue();
                    $data['is_tipe'] = 1;
                    $data['is_deleted'] = 0;
                    $tes[] = $data;
                    $cekid = $db->select("*")->from("acc_m_akun")->where("id", "=", $id)->find();
                    if($cekid){
                        $update = $db->update("acc_m_akun", $data, ["id"=>$id]);
                    }else{
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
 * export
 */
$app->get('/acc/m_klasifikasi/export', function ($request, $response) {

    $inputFileName = 'acc/landaacc/upload/format_tipeakun.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($inputFileName);

    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=format_tipeakun.xls");

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});

$app->get('/acc/m_klasifikasi/indexLama', function ($request, $response) {

    $params = $request->getParams();

    $filter = array();
    $sort = " kode ASC";
    $offset = 0;
    $limit = 10;

    if (isset($params['limit'])) {
        $limit = $params['limit'];
    }

    if (isset($params['offset'])) {
        $offset = $params['offset'];
    }

    if (isset($params['sort'])) {
        $sort = $params['sort'];
        if (isset($params['order'])) {
            if ($params['order'] == "false") {
                $sort .= " ASC";
            } else {
                $sort .= " DESC";
            }
        }
    }

    $db = $this->db;
    $db->select("m_akun.*")
        ->from('m_akun')
        ->limit($limit)
        ->orderBy($sort)
        ->offset($offset)
        ->where('is_tipe', '=', '1');

    if (isset($params['filter'])) {
        $filter = (array)json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            $db->where($key, 'LIKE', $val);
        }
    }

    $models = $db->findAll();

    $returned=[];
    foreach ($models as $key => $val) {
        $spasi = ($val->level == 1) ? '' : str_repeat("···", $val->level - 1);

        $returned[$key]                 = (array)$val;
        $returned[$key]['namaroot']     = $spasi . $val->nama;
        $returned[$key]['parent_id']    = (string)$val->parent_id;
        $returned[$key]['klasifikasi']  = $val->tipe;
        // $models[$key]['is_pendapatan_usaha'] = (string) $val->is_pendapatan_usaha;

        // if ($models[$key]['klasifikasi'] == 'Receivable' || $models[$key]['klasifikasi'] == 'Piutang Usaha' || $models[$key]['klasifikasi'] == 'Piutang Lain') {
        //     $models[$key]['klasifikasi'] = 'Piutang';
        //
        // }else if ($models[$key]['klasifikasi'] == 'Payable' || $models[$key]['klasifikasi'] == 'Hutang Lancar' || $models[$key]['klasifikasi'] == 'Hutang Tidak Lancar'){
        //     $models[$key]['klasifikasi'] = 'Hutang';
        //
        // }else if ($models[$key]['klasifikasi'] == 'Harta'){
        //     $models[$key]['klasifikasi'] = 'Bangunan dan Peralatan';
        // }

    }
    $totalItem = $db->count();

    return successResponse($response, ['list' => $returned, 'totalItems' => $totalItem]);
});

function setLevelTipeAkun($parent_id)
{

    $db = new Cahkampung\Landadb(config('DB')['db']);
    $parent = $db->find("select * from acc_m_akun where id = '" . $parent_id . "'");
    return $parent->level + 1;
}


