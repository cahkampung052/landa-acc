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
        "parent_id" => "required",
    );
    GUMP::set_field_name("parent_id", "Klasifikasi Induk");
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
 * Ambil semua klasifikasi akun
 */
$app->get('/acc/m_klasifikasi/index', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    /**
     * Ambil semua klasifikasi
     */
    $db->select('acc_m_akun.*, induk.kode as kode_induk')
        ->from('acc_m_akun')
        ->leftJoin('acc_m_akun as induk', 'induk.id = acc_m_akun.parent_id')
        ->where('acc_m_akun.is_tipe', '=', '1')
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
    $arr = array();
    foreach ($models as $key => $value) {
        $spasi                      = ($value->level == 1) ? '' : str_repeat("···", $value->level - 1);
        $value->nama_lengkap        = $spasi . $value->kode . ' - ' . $value->nama;
        $value->parent_id           = $value->parent_id == 0 ? (string) $value->parent_id : (int) $value->parent_id;
        $value->kode                = ($value->id <= 9) ? $value->kode : str_replace($value->kode_induk.".", "", $value->kode);
        $value->tipe                = ($value->tipe == 'No Type') ? '' : $value->tipe;
    }
    /**
     * Kirim response ke client
     */
    return successResponse($response, ['list' => $models]);
});
/**
 * Ambil list klasifikasi
 */
$app->get('/acc/m_klasifikasi/list', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("acc_m_akun.*")
        ->from('acc_m_akun')
        ->where('is_tipe', '=', 1)
        ->where('is_deleted', '=', 0)
        ->orderBy('acc_m_akun.kode ASC')
        ->findAll();
    /**
     * Kirim response ke server
     */
    return successResponse($response, ['list' => $models]);
});
/**
 * Simpan klasifikasi
 */
$app->post('/acc/m_klasifikasi/save', function ($request, $response) {
    $data   = $request->getParams();
    $db     = $this->db;
    $data['tipe'] = isset($data['tipe']) ? $data['tipe'] : '';
    $data['nama'] = isset($data['nama']) ? $data['nama'] : '';
    $data['parent_id'] = isset($data['parent_id']) ? $data['parent_id'] : '';
    $data['is_tipe'] = 1;
    if ($data['parent_id'] == 0 || $data['parent_id'] == '') {
        $validasi = validasi($data, ['tipe' => 'required']);
    } else {
        $validasi = validasi($data);
    }
    if ($validasi === true) {
        $data['kode'] = $data['parent_id'] == 0 ? $data['kode'] : $data['kode_induk'] . '' . $data['kode'];
        if ($data['parent_id'] == 0 || $data['parent_id'] == '') {
            $data['level'] = 1;
        } else {
            $data['level'] = setLevelTipeAkun($data['parent_id']);
            /**
             * ambil tipe akun di atasnya
             */
            $getparent = $db->select('*')->from('acc_m_akun')->where('id', '=', $data['parent_id'])->find();
            $data['tipe'] = $getparent->tipe;
        }
        
        // $childId = getChildId("acc_m_akun", $data['id']);
        
        /**
         * Simpan ke database
         */
        if (isset($data['id']) && !empty($data['id'])) {
            $model = $db->update('acc_m_akun', $data, ['id' => $data['id']]);
        } else {
            $model = $db->insert('acc_m_akun', $data);
        }

        /**
         * Update saldo Normal
         */
        $db->run("update acc_m_akun set saldo_normal = 1 where tipe = 'HARTA'");
        $db->run("update acc_m_akun set saldo_normal = -1 where tipe = 'KEWAJIBAN'");
        $db->run("update acc_m_akun set saldo_normal = -1 where tipe = 'MODAL'");
        $db->run("update acc_m_akun set saldo_normal = -1 where tipe = 'PENDAPATAN'");
        $db->run("update acc_m_akun set saldo_normal = -1 where tipe = 'PENDAPATAN DILUAR USAHA'");
        $db->run("update acc_m_akun set saldo_normal = 1 where tipe = 'BEBAN'");
        $db->run("update acc_m_akun set saldo_normal = 1 where tipe = 'BEBAN DILUAR USAHA'");
        
        /**
         * Update tipe akun dibawahnya
         */
        $childId =getChildId("acc_m_akun", $model->id);
        if(!empty($childId)){
            $db->update("acc_m_akun", ["tipe" => $model->tipe, "tipe_arus" => $model->tipe_arus], "id in (".implode(",", $childId).")");            
        }
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, $validasi);
    }
});
/**
 * Hapus klasifikasi
 */
$app->post('/acc/m_klasifikasi/trash', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $update['is_deleted'] = $data['is_deleted'];
    $update['tgl_nonaktif'] = date('Y-m-d');
    try {
        $getChild = getChildId("acc_m_akun", $data['id']);
        if(!empty($getChild) && $data['is_deleted'] == 1){
            return unprocessResponse($response, ['Klasifikasi tidak dapat dihapus karena memiliki sub klasifikasi']);            
        }
        $model = $db->update("acc_m_akun", $update, ['id' => $data['id']]);
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ['Data Gagal Di Simpan']);
    }
});

/**
 * export
 */
$app->get('/acc/m_klasifikasi/export', function ($request, $response) {
    $inputFileName = 'acc/landaacc/file/format_tipeakun.xls';
    $objReader = PHPExcel_IOFactory::createReader('Excel5');
    $objPHPExcel = $objReader->load($inputFileName);
    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;Filename=format_tipeakun.xls");
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
});
