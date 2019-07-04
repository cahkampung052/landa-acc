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
//        "tipe" => "required"
    );
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
    /** 
     * set parameter
     */
    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where('acc_m_akun.is_deleted', '=', $val);
            } elseif ($key=="nama") {
                $db->where('acc_m_akun.nama', 'LIKE', $val);
            } elseif ($key=="kode") {
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
        $arr[$key]['kode']                = $value->kode;
        if ($value->tipe == 'No Type') {
            $arr[$key]['tipe'] = '';
        }
    }
    return successResponse($response, ['list' => $arr, 'totalItems' => $totalItem]);
});
/**
 * Ambil list klasifikasi
 */
$app->get('/acc/m_klasifikasi/list', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("acc_m_akun.*")
        ->from('acc_m_akun')
        ->where('is_tipe', '=', '1')
        ->where('is_deleted', '=', 0)
        ->orderBy('acc_m_akun.kode ASC')
        ->findAll();
    return successResponse($response, ['list' => $models]);
});
/**
 * Create klasifikasi
 */
$app->post('/acc/m_klasifikasi/create', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
//    print_r($data);die();
    $data['tipe'] = isset($data['tipe']) ? $data['tipe'] : '';
    $validasi = validasi($data);
    if ($validasi === true) {
        $data['is_tipe'] = 1;
        $data['kode'] = $data['parent_id'] == 0 ? $data['kode'] : $data['kode_induk'] . '.' . $data['kode'];
        if ($data['parent_id'] == 0) {
            $data['level'] = 1;
        } else {
            $data['level'] = setLevelTipeAkun($data['parent_id']);
            $getparent = $db->select("*")->from("acc_m_akun")->where("id", "=", $data['parent_id'])->find();
            $data['tipe'] = $getparent->nama;
        }
//        print_r($data);die();
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
/**
 * Update klasifikasi
 */
$app->post('/acc/m_klasifikasi/update', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $validasi = validasi($data);
    if ($validasi === true) {
        $data['is_tipe'] = 1;
        $model = $db->update("acc_m_akun", $data, array('id' => $data['id']));
        /** 
         * Update tipe di semua akun
         */
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
/**
 * Non aktifkasn klasifikasi
 */
$app->post('/acc/m_klasifikasi/trash', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $data['is_deleted'] = $data['is_deleted'];
    $data['tgl_nonaktif'] = date('Y-m-d');
    try {
        $model = $db->update("acc_m_akun", $data, array('id' => $data['id']));
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ['Data Gagal Di Simpan']);
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
                    if ($cekid) {
                        $update = $db->update("acc_m_akun", $data, ["id"=>$id]);
                    } else {
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
