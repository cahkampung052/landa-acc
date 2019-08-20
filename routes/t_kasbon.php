<?php

date_default_timezone_set('Asia/Jakarta');

function validasi($data, $custom = array())
{
    $validasi = array(
        'cabang' => 'required',
        'karyawan' => 'required',
//        'no_transaksi'      => 'required',
        'akun'      => 'required',
        'tanggal' => 'required',
        'total' => 'required',
    );
    
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/t_kasbon/index', function ($request, $response) {
    $params = $request->getParams();
     $sort     = "acc_kasbon.created_at DESC";
    $offset   = isset($params['offset']) ? $params['offset'] : 0;
    $limit    = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
     $db->select("
            acc_kasbon.*,
            akun_asal.nama as akun_asal_nama,
            akun_asal.kode as akun_asal_kode,
            akun_tujuan.nama as akun_tujuan_nama,
            akun_tujuan.kode as akun_tujuan_kode,
            user.nama as dibuat_oleh,
            m_unker.nama as nama_cabang,
            m_unker.kode as kode_cabang,
            pegawai.id as pegawai_id
        ")
        ->from('acc_kasbon')
        ->leftJoin('acc_m_akun as akun_asal', 'akun_asal.id = acc_kasbon.m_akun_asal')
        ->leftJoin('acc_m_akun as akun_tujuan', 'akun_tujuan.id = acc_kasbon.m_akun_tujuan')
        ->leftJoin('karyawan as user', 'user.id = acc_kasbon.created_by')
        ->leftJoin('m_unker', 'm_unker.id = acc_kasbon.m_unker_id')
        ->leftJoin('karyawan as pegawai', 'pegawai.id = acc_kasbon.karyawan_id')
        ->orderBy($sort);
//        ->where("acc_pemasukan.is_deleted", "=", 0);

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_kasbon.is_deleted", '=', $val);
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
    
    foreach ($models as $key => $val) {
        $val->akun   = ['nama' => $val->akun_asal_nama, 'id' => $val->m_akun_asal, 'kode' => $val->akun_asal_kode];
        $val->akun_tujuan = ['nama' => $val->akun_tujuan_nama, 'id' => $val->m_akun_tujuan, 'kode' => $val->akun_tujuan_kode];
        $val->cabang      = ['nama' => $val->nama_cabang, 'id' => $val->m_unker_id, 'kode' => $val->kode_cabang];
        $val->tanggal_new = strtotime($val->tanggal);
        $val->status        = ucfirst($val->status);
        $getKaryawan        = $db->select("*")->from("karyawan")->where("id", "=", $val->karyawan_id)->find();
        $val->karyawan   = $getKaryawan;
        $val->is_tutuptahun = false;
        $tahun              = date('Y');
        $bulan              = date('m');
        $tutup_thn          = cektutuptahun(isset($val->m_unker_id) ? $val->m_unker_id : null, $tahun);
        $tgl                = '';
//
        if (!empty($tutup_thn) && empty($tutup_thn->bulan)) {
            $val->is_tutuptahun = true;
        } else {

            $tutup_bulan = cektutuptahun(isset($val->m_unker_id) ? $val->m_unker_id : null, $tahun, $bulan);
            if (!empty($tutup_bulan)) {
                $val->is_tutuptahun = true;
            }
        }
    }
    
    
//     print_r($models);exit();
//    die();
//      print_r($arr);exit();
    return successResponse($response, [
      'list'        => $models,
      'totalItems'  => $totalItem,
      'base_url'    => str_replace('api/', '', config('SITE_URL'))
    ]);
});


$app->post('/acc/t_kasbon/save', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;
//    print_r($data);die();
    $sql = $this->db;
    $validasi = validasi($data);
    if ($validasi === true) {
        
        /*
        * akun pengimbang
        */
        $getakun = $sql->select("*")->from("acc_m_akun_peta")->where("type", "=", "Kasbon Karyawan")->find();

        /*
         * kode
         */
        $kode = generateNoTransaksi("kasbon", $params['cabang']['kode']);
//        print_r($kode);die();
        

        $insert['karyawan_id'] = $data['karyawan']['id'];
        $insert['m_akun_asal'] = $data['akun']['id'];
        $insert['m_akun_tujuan'] = $getakun->m_akun_id;
        $insert['m_so_id'] = $data['cabang']['m_so_id'];
        $insert['m_departemen_id'] = $data['cabang']['m_departemen_id'];
        $insert['m_unker_id'] = $data['cabang']['m_unker_id'];
        $insert['tanggal'] = date("Y-m-d h:i:s",strtotime($data['tanggal']));
        $insert['total'] = $data['total'];
        $insert['status'] = $data['status'];
        $insert['keterangan'] = (isset($data['keterangan']) && !empty($data['keterangan']) ? $data['keterangan'] : '');
//        print_r($insert);die();
        if (isset($data['id']) && !empty($data['id'])) {
            $insert['no_urut'] = $data['no_urut'];
            $insert['no_transaksi'] = $data['no_transaksi'];
            $model = $sql->update("acc_kasbon", $insert, ["id" => $data['form']['id']]);
        } else {
            $insert['no_transaksi'] = $kode;
            $insert['no_urut'] = (empty($kode)) ? 1 : ((int) substr($kode, -5));
            $model = $sql->insert("acc_kasbon", $insert);
        }
        
        //delete transdetail
        $deletetransdetail = $sql->delete("acc_trans_detail", ["reff_id"=>$model->id, "reff_type"=>"acc_kasbon"]);

        /*
         * deklarasi untuk simpan ke transdetail
         */
        $index = 0;
        $transDetail = [];
        
        $transDetail[0]['m_lokasi_id'] = $data['cabang']['m_lokasi_id'];
        $transDetail[0]['m_kontak_id'] = $data['karyawan']['m_kontak_id'];
        $transDetail[0]['m_akun_id'] = $data['akun']['id'];
        $transDetail[0]['tanggal'] = date("Y-m-d",strtotime($data['tanggal']));
        $transDetail[0]['debit'] = $data['total'];
        $transDetail[0]['reff_type'] = "acc_kasbon";
        $transDetail[0]['reff_id'] = $model->id;
        $transDetail[0]['keterangan'] = (isset($data['keterangan']) && !empty($data['keterangan']) ? $data['keterangan'] : '');
        $transDetail[0]['kode'] = $model->no_transaksi;
        
        $transDetail[1]['m_lokasi_id'] = $data['cabang']['m_lokasi_id'];
        $transDetail[1]['m_kontak_id'] = $data['karyawan']['m_kontak_id'];
        $transDetail[1]['m_akun_id'] = $getakun->m_akun_id;
        $transDetail[1]['tanggal'] = date("Y-m-d",strtotime($data['tanggal']));
        $transDetail[1]['kredit'] = $data['total'];
        $transDetail[1]['reff_type'] = "acc_kasbon";
        $transDetail[1]['reff_id'] = $model->id;
        $transDetail[1]['kode'] = $model->no_transaksi;
        $transDetail[1]['keterangan'] = (isset($data['keterangan']) && !empty($data['keterangan']) ? $data['keterangan'] : '');
        
        
//        print_r($transDetail);die();
        /*
         * Simpan array trans detail ke database jika simpan dan kunci
         */
        if($data['status'] == "terposting"){
            insertTransDetail($transDetail);
        }
        
        
        
        if ($model) {
            
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});


$app->post('/acc/t_transfer/delete', function ($request, $response) {

    $data = $request->getParams();
    $db   = $this->db;

    
    $model = $db->delete("acc_transfer", ['id' => $data['id']]);
    $model = $db->deleted("acc_trans_detail", ["reff_type" => "acc_transfer", "reff_id" => $data['id']]);
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
