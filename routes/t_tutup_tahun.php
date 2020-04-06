<?php
function validasi($data, $custom = array())
{
    $validasi = array();
    $cek = validate($data, $validasi, $custom);
    return $cek;
}
$app->get('/acc/t_tutup_tahun/index', function ($request, $response) {
    $params     = $request->getParams();
    $tableuser  = tableUser();
    $db         = $this->db;
    $db->select("
        acc_tutup_buku.*,
        $tableuser.nama as namaUser
      ")
            ->from("acc_tutup_buku")
            ->leftJoin($tableuser, $tableuser . ".id=acc_tutup_buku.created_by")
            ->where("acc_tutup_buku.jenis", "=", "tahun");
    /** Add filter */
    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            if ($key == "tahun") {
                if ($val != "semua") {
                    $db->where($key, '=', $val);
                }
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
    $models     = $db->findAll();
    $totalItem  = $db->count();
    $array      = [];
    foreach ($models as $key => $val) {
        $akun_ikhtisar = $db->find("select * from acc_m_akun where id = '".$val->akun_ikhtisar_id."'");
        $akun_pemindaian = $db->find("select * from acc_m_akun where id = '".$val->akun_pemindahan_modal_id."'");
        $tgl = date('Y-m-d', strtotime($val->tahun . '-' . $val->bulan . '-01'));
        $array[$key] = (array) $val;
        $array[$key]['akun_ikhtisar_id'] = $akun_ikhtisar;
        $array[$key]['akun_pemindahan_modal_id'] = $akun_pemindaian;
        $array[$key]['hasil_rp'] = rp($val->hasil_lr);
        $array[$key]['bln_tahun'] = $tgl;
        $array[$key]['created_at'] = date("d-m-Y", $val->created_at);
    }
    return successResponse($response, ['list' => $array, 'totalItems' => $totalItem]);
});
$app->get('/acc/t_tutup_tahun/tahun', function ($request, $response) {
    $db = $this->db;
    $list = $db->findAll("select * from acc_tutup_buku");
    $list_tahun = [];
    foreach ($list as $val) {
        $list_tahun[] = $val->tahun;
    }
    $tahun = range(date("Y") - 3, date("Y") + 1);
    $listtahun = array_unique(array_merge($list_tahun, $tahun));
    return successResponse($response, $tahun);
});
$app->get('/acc/t_tutup_tahun/getView', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $labaRugi = $pemindahan_modal = $prive = [];
    if(isset($params['id']) && !empty($params['id'])){
        $list = $db->select("
            acc_tutup_buku_det.*, 
            acc_m_akun.nama, 
            acc_m_akun.kode,
            acc_m_akun.is_tipe,
            acc_m_akun.tipe as tipe_akun,
            acc_m_akun.kode,
            acc_m_akun.saldo_normal
        ")
        ->from("acc_tutup_buku_det")
        ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_tutup_buku_det.m_akun_id")
        ->where("acc_tutup_buku_det.acc_tutup_buku_id", "=", $params["id"])
        ->findAll();
        foreach ($list as $key => $value) {
            if ($value->tipe == 'laba_rugi' ) {
                $nominal = ($value->debit > $value->kredit) ? $value->debit : $value->kredit;
                $value->tipe_akun = str_replace(" ", "_", $value->tipe_akun);
                if($value->is_tipe == 0){
                    $labaRugi[$value->tipe_akun]['total'] = (isset($labaRugi[$value->tipe_akun]['total']) ? $labaRugi[$value->tipe_akun]['total'] : 0) + ($nominal);                    
                }
                $labaRugi[$value->tipe_akun]['detail'][] = [
                    "is_tipe" => $value->is_tipe,
                    "id" => $value->m_akun_id,
                    "kode" => $value->kode,
                    "nama" => $value->kode. " - ". $value->nama,
                    "nominal" => $nominal,
                ];
            }else if($value->tipe == 'pemindahan_modal'){
                $pemindahan_modal[] = [
                    "id" => $value->m_akun_id,
                    "kode" => $value->kode,
                    "nama" => $value->kode. " - ". $value->nama,
                    "debit" => $value->debit,
                    "kredit" => $value->kredit,
                ];
            }else if($value->tipe == 'prive'){
                $prive[] = [
                    "id" => $value->m_akun_id,
                    "kode" => $value->kode,
                    "nama" => $value->kode. " - ". $value->nama,
                    "debit" => $value->debit,
                    "kredit" => $value->kredit
                ];
            }
        }
    }
    return successResponse($response, [
        'detail' => $labaRugi,
        'jurnalPemindahan' => $pemindahan_modal,
        'jurnalPrive' => $prive
    ]);
});
$app->get('/acc/t_tutup_tahun/getDetail', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    /**
     * Laba Rugi
     */
    $tahun = date("Y", strtotime($params["tahun"]));
    $tanggal_start = $tahun."-01-01";
    $tanggal_end = $tahun."-12-31";
    $labarugi = getLabaRugi($tanggal_start, $tanggal_end, null);
    $arr = $labarugi['data'];
    /**
     * Total pendapatan
     */
    $pendapatan = isset($labarugi['total']['PENDAPATAN']) ? $labarugi['total']['PENDAPATAN'] : 0;
    $pendapatanLuarUsaha = isset($labarugi['total']['PENDAPATAN DILUAR USAHA']) ? $labarugi['total']['PENDAPATAN DILUAR USAHA'] : 0;
    /**
     * Total beban
     */
    $beban = isset($labarugi['total']['BEBAN']) ? $labarugi['total']['BEBAN'] : 0;
    $bebanLuarUsaha = isset($labarugi['total']['BEBAN DILUAR USAHA']) ? $labarugi['total']['BEBAN DILUAR USAHA'] : 0;
    /**
     * Param lainnya
     */
    $data['total'] = $pendapatan + $pendapatanLuarUsaha - $beban - $bebanLuarUsaha;
    $data['lr_usaha'] = $pendapatan - $beban;
    $data['is_detail'] = true;
    foreach ($arr as $key => $value) {
        foreach ($value['detail'] as $keys => $values) {
            if ($values['nominal'] == 0) {
                unset($arr[$key]['detail'][$keys]);
            }
        }
    }
    /**
     * Jurnal pemindahan modal
     */
    if ($data['total'] >= 0) {
        $jurnalPemindahan[] = [
            'nama' => isset($params['akun_ikhtisar_nama']) ? $params['akun_ikhtisar_nama'] : '',
            'id' => isset($params['akun_ikhtisar_id']) ? $params['akun_ikhtisar_id'] : '',
            'debit' => $data['total'],
            'kredit' => '',
        ];
        $jurnalPemindahan[] = [
            'nama' => isset($params['akun_pemindahan_modal_nama']) ? $params['akun_pemindahan_modal_nama'] : '',
            'id' => isset($params['akun_pemindahan_modal_id']) ? $params['akun_pemindahan_modal_id'] : '',
            'debit' => '',
            'kredit' => $data['total'],
        ];
    } else {
        $jurnalPemindahan[] = [
            'nama' => isset($params['akun_pemindahan_modal_nama']) ? $params['akun_pemindahan_modal_nama'] : '',
            'id' => isset($params['akun_pemindahan_modal_id']) ? $params['akun_pemindahan_modal_id'] : '',
            'debit' => $data['total'],
            'kredit' => '',
        ];
        $jurnalPemindahan[] = [
            'nama' => isset($params['akun_ikhtisar_nama']) ? $params['akun_ikhtisar_nama'] : '',
            'id' => isset($params['akun_ikhtisar_id']) ? $params['akun_ikhtisar_id'] : '',
            'debit' => '',
            'kredit' => $data['total'],
        ];
    }
    /**
     * Ambil prive
     */
    $priveId        = getPemetaanAkun('Prive');
    $akunPrive      = isset($priveId[0]) ? $priveId[0] : 0;
    $saldoPrive     = getSaldo($akunPrive, null, $tanggal_start, $tanggal_end);
    $jurnalPrive    = [];
    if ($saldoPrive > 0) {
        $nama = $db->select("nama")->from("acc_m_akun")->where("id", "=", $akunPrive)->find();
        $jurnalPrive[] = [
            'nama' => isset($params['akun_pemindahan_modal_nama']) ? $params['akun_pemindahan_modal_nama'] : '',
            'id' => isset($params['akun_pemindahan_modal_id']) ? $params['akun_pemindahan_modal_id'] : '',
            'debit' => $saldoPrive,
            'kredit' => '',
        ];
        $jurnalPrive[] = [
            'nama' => isset($nama->nama) ? $nama->nama : '',
            'id' => $akunPrive,
            'debit' => '',
            'kredit' => $saldoPrive,
        ];
    }
    return successResponse($response, [
        'detail' => $arr,
        'data' => $data,
        'jurnalPemindahan' => $jurnalPemindahan,
        'jurnalPrive' => $jurnalPrive
    ]);
});
$app->post('/acc/t_tutup_tahun/save', function ($request, $response) {
    $params     = $request->getParams();
    $data       = $params["form"];
    $db         = $this->db;
    $validasi   = validasi($data);
    $total      = 0;
    /**
     * Ambil lokasi pertama
     */
    $lokasi = $db->select("*")->from("acc_m_lokasi")->orderBy("parent_id ASC, level ASC")->find();
    if ($validasi === true) {
        $cekData = $db->select("*")->from("acc_tutup_buku")
                ->where("jenis", "=", "tahun")
                ->where("tahun", "=", $data['tahun'])
                ->count();
        if ($cekData > 0) {
            return unprocessResponse($response, 'Data Sudah Ada');
            die();
        }
        /*
         * insert acc_tutup_buku
         */
        $data['jenis'] = "tahun";
        $data['tahun'] = date("Y", strtotime($params["form"]['tahun']));
        $data['akun_ikhtisar_id'] = $params["form"]['akun_ikhtisar_id']['id'];
        $data['akun_pemindahan_modal_id'] = $params["form"]['akun_pemindahan_modal_id']['id'];
        $model = $db->insert("acc_tutup_buku", $data);
        if ($model) {
            $arr = [];
            if(isset($params['detail']['PENDAPATAN']['detail']) && !empty($params['detail']['PENDAPATAN']['detail'])){
                foreach ($params['detail']['PENDAPATAN']['detail'] as $key => $value) {
                    $arr[] = [
                        "acc_tutup_buku_id" => $model->id,
                        "m_akun_id" => $value['id'],
                        "debit" => $value['nominal'],
                        "kredit" => 0,
                        "tipe" => "laba_rugi"
                    ];
                    if($value['is_tipe'] == 0){
                        $total += $value['nominal'];                        
                    }
                }
            }
            if(isset($params['detail']['PENDAPATAN_DILUAR_USAHA']['detail']) && !empty($params['detail']['PENDAPATAN_DILUAR_USAHA']['detail'])){
                foreach ($params['detail']['PENDAPATAN_DILUAR_USAHA']['detail'] as $key => $value) {
                    $arr[] = [
                        "acc_tutup_buku_id" => $model->id,
                        "m_akun_id" => $value['id'],
                        "debit" => $value['nominal'],
                        "kredit" => 0,
                        "tipe" => "laba_rugi"
                    ];
                    if($value['is_tipe'] == 0){
                        $total += $value['nominal'];                        
                    }
                }
            }
            if(isset($params['detail']['BEBAN']['detail']) && !empty($params['detail']['BEBAN']['detail'])){
                foreach ($params['detail']['BEBAN']['detail'] as $key => $value) {
                    $arr[] = [
                        "acc_tutup_buku_id" => $model->id,
                        "m_akun_id" => $value['id'],
                        "debit" => 0,
                        "kredit" => $value['nominal'],
                        "tipe" => "laba_rugi"
                    ];
                    if($value['is_tipe'] == 0){
                        $total -= $value['nominal'];                        
                    }
                }
            }
            if(isset($params['detail']['BEBAN_DILUAR_USAHA']['detail']) && !empty($params['detail']['BEBAN_DILUAR_USAHA']['detail'])){
                foreach ($params['detail']['BEBAN_DILUAR_USAHA']['detail'] as $key => $value) {
                    $arr[] = [
                        "acc_tutup_buku_id" => $model->id,
                        "m_akun_id" => $value['id'],
                        "debit" => 0,
                        "kredit" => $value['nominal'],
                        "tipe" => "laba_rugi"
                    ];
                    if($value['is_tipe'] == 0){
                        $total -= $value['nominal'];                        
                    }
                }
            }
            if(isset($params['jurnalPemindahan']) && !empty($params['jurnalPemindahan'])){
                foreach ($params['jurnalPemindahan'] as $key => $value) {
                    $arr[] = [
                        "acc_tutup_buku_id" => $model->id,
                        "m_akun_id" => $value['id'],
                        "debit" => $value['debit'],
                        "kredit" => $value['kredit'],
                        "tipe" => "pemindahan_modal"
                    ];       
                }   
            }
            if(isset($params['jurnalPrive']) && !empty($params['jurnalPrive'])){
                foreach ($params['jurnalPrive'] as $key => $value) {
                    $arr[] = [
                        "acc_tutup_buku_id" => $model->id,
                        "m_akun_id" => $value['id'],
                        "debit" => $value['debit'],
                        "kredit" => $value['kredit'],
                        "tipe" => "prive"
                    ];    
                }
            }
            if(!empty($arr)){
                multiInsert("acc_tutup_buku_det", $arr);                
                /**
                 * Simpan jurnal
                 */
                $tmp = [];
                foreach ($arr as $key => $value) {
                    $tmp[$key]['m_akun_id'] = $value['m_akun_id'];
                    $tmp[$key]['debit'] = $value['debit'];
                    $tmp[$key]['kredit'] = $value['kredit'];
                    $tmp[$key]['keterangan'] = "Tutup buku tahun " . $params['tahun'];
                    $tmp[$key]['kode'] = "TB-".$params['tahun'];
                    $tmp[$key]['reff_id'] = $model->id;
                    $tmp[$key]['reff_type'] = "acc_tutup_buku";
                    $tmp[$key]['tanggal'] = ($params['tahun'])."-12-31";
                    $tmp[$key]['m_lokasi_jurnal_id'] = $lokasi->id;
                    $tmp[$key]['m_lokasi_id'] = $lokasi->id;
                    $tmp[$key]['created_at'] = strtotime($tmp[$key]['tanggal']." 23:55:00");
                }
                if(!empty($tmp)){
                    multiInsert("acc_trans_detail", $tmp);
                }
                /**
                 * Update laba rugi 
                 */
                $db->update("acc_tutup_buku", ["hasil_lr" => $total], ["id" => $model->id]);
            }
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, 'Data Gagal Di Simpan');
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});
$app->post('/acc/t_tutup_tahun/delete', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $model = $db->delete("acc_tutup_buku", ['id' => $data['id']]);
    $modelDetail = $db->delete("acc_tutup_buku_det", ['acc_tutup_buku_id' => $data['id']]);
    $modelTransDetail = $db->delete("acc_trans_detail", ['reff_id' => $data['id'], 'reff_type' => 'acc_tutup_buku']);
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
