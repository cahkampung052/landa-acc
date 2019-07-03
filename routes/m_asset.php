<?php

function validasi($data, $custom = array())
{
    $validasi = array(
        'nama'   => 'required',
        'harga'  => 'required',
        'lokasi' => 'required',
        'is_penyusutan' => 'required',
        'akun_asset' => 'required',
        'akun_akumulasi' => 'required',
        'akun_beban' => 'required',
    );
   GUMP::set_field_name("akun_asset", "Akun Asset");
   GUMP::set_field_name("akun_akumulasi", "Akun Akumulasi Penyusutan");
   GUMP::set_field_name("akun_beban", "Akun Beban Penyusutan");
   GUMP::set_field_name("harga", "Nilai Perolehan");
   GUMP::set_field_name("is_penyusutan", "Penyusutan");
   GUMP::set_field_name("umur", "Umur Ekonomis");
   GUMP::set_field_name("persentase", "Tarif Depresiasi");
   GUMP::set_field_name("nilai_residu", "Nilai Residu");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

function validasi_pelepasan($data, $custom = array())
{
    $validasi = array(
        'status' => 'required',
    );
    GUMP::set_field_name("status", "Jenis Pelepasan");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}


$app->get('/acc/m_asset/getDetailPenyusutan', function ($request, $response) {
    $params = $request->getParams();

    $sql = $this->db;
    $sql->select("*")->from("acc_asset")
        ->where("id", "=", $params["id"]);
    $models = $sql->find();

    $tahun = date("Y",strtotime($models->tanggal_beli));
    $batas_bulan = date('m', strtotime('-1 months', strtotime($models->tanggal_beli)));

    $batas_tahun = $tahun + $models->tahun; $dt = [];
    for ($i=$tahun; $i <= $batas_tahun ; $i++) {
        if ($i==$tahun) {
            $dt[$i]['saldo_awal'] = $models->harga_beli;
            $dt[$i]['awal'] = date("t M Y",strtotime($models->tanggal_beli));
            $dt[$i]['akhir'] = date("t M Y", strtotime($i."-12-01"));

            //format
            $dt[$i]['awal_default'] = date("Y-m-t",strtotime($models->tanggal_beli));
            $dt[$i]['akhir_default'] = date("Y-m-t", strtotime($i."-12-01"));
        }else if ($i==$batas_tahun) {
            $dt[$i]['awal'] = date("t M Y",strtotime($i."-01-01"));
            $dt[$i]['akhir'] = date("t M Y",strtotime($i."-".$batas_bulan."-01")); 

            //format
            $dt[$i]['awal_default'] = date("Y-m-d",strtotime($i."-01-01"));
            $dt[$i]['akhir_default'] = date("Y-m-t",strtotime($i."-".$batas_bulan."-01")); 
            $dt[$i]['saldo_awal'] = $dt[$i-1]['saldo_akhir']; ; 
        }else{
            $dt[$i]['awal'] = date("t M Y",strtotime($i."-01-01"));
            $dt[$i]['akhir'] = date("t M Y", strtotime($i."-12-01"));

            //
            $dt[$i]['awal_default'] = date("Y-m-t",strtotime($i."-01-01"));
            $dt[$i]['akhir_default'] = date("Y-m-t", strtotime($i."-12-01"));
            $dt[$i]['saldo_awal'] = $dt[$i-1]['saldo_akhir']; 
        }


        //date time
        $datetime1 = new DateTime($dt[$i]['awal_default']);
        $datetime2 = new DateTime($dt[$i]['akhir_default']);
        $interval = $datetime1->diff($datetime2);
        $dt[$i]['selisih'] = $interval->m + 1;
        $dt[$i]['persentase'] = $models->persentase;

        $dt[$i]['penyusutan_pertahun'] = round($dt[$i]['selisih']/12 * ($dt[$tahun]['saldo_awal']- $models->nilai_residu) * ($models->persentase/100),2); 

        $dt[$i]['penyusutan_perbulan'] = round($dt[$i]['penyusutan_pertahun']/$dt[$i]['selisih'],2);
        $dt[$i]['saldo_akhir'] = round(($dt[$i]['saldo_awal'] - $dt[$i]['penyusutan_pertahun']),2);
    }


    return successResponse($response, [
        'list'     => $dt,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
    ]);
});
$app->get('/acc/m_asset/getAkun', function ($request, $response) {
    $params = $request->getParams();

    $sql = $this->db;
    $sql->select("*")->from("m_akun")
        ->where("is_deleted", "=", 0)
        ->andWhere("is_tipe", "=", 0);
    $models = $sql->findAll();
    return successResponse($response, [
        'list'     => $models,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
    ]);
});


$app->get('/acc/m_asset/index', function ($request, $response) {
    $params = $request->getParams();
    // $sort     = "m_akun.kode ASC";
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit  = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("acc_asset.*,m_lokasi.nama as nm_lokasi,acc_umur_ekonomis.nama as nama_umur, acc_umur_ekonomis.tahun as tahun_umur, acc_umur_ekonomis.persentase as persentase_umur")
        ->from("acc_asset")
        ->leftJoin("m_lokasi", "m_lokasi.id = acc_asset.lokasi_id")
        ->leftJoin("acc_umur_ekonomis", "acc_umur_ekonomis.id = acc_asset.umur_ekonomis")
        ->orderBy('acc_asset.id DESC');

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_asset.is_deleted", '=', $val);
            } else if ($key == 'nama') {
                $db->where("acc_asset.nama", 'LIKE', $val);
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
        $value->tanggal_beli_format = date("d-m-Y",strtotime($value->tanggal_beli));
        $value->lokasi = ["id" => $value->lokasi_id, "nama" => $value->nm_lokasi];
        $value->umur = ["id" => $value->umur_ekonomis, "nama" => $value->nama_umur, "tahun" => $value->tahun_umur, "persentase" => $value->persentase_umur];
        $value->persentase = (Float) $value->persentase;
        $value->akun = ["id"=>"","nama"=>""];
    }
//     print_r($models);exit();

//      print_r($arr);exit();
    return successResponse($response, [
        'list'       => $models,
        'totalItems' => $totalItem,
        'base_url'   => str_replace('api/', '', config('SITE_URL')),
    ]);
});

$app->post('/acc/m_asset/save', function ($request, $response) {

    $params = $request->getParams();
    $data   = $params;
    $sql    = $this->db;

    if ($data["is_penyusutan"]==1) {
        $validasi = validasi($data,["umur"=>'required',"persentase"=>'required',"nilai_residu"=>'required']);
    }else{
        $validasi = validasi($data);
    }

    if ($validasi === true) {
        $data["lokasi_id"]    = $data["lokasi"]["id"];
        $data["nama_lokasi"] = $data["lokasi"]["nama"];
        $data["akun_asset_id"]    = $data["akun_asset"]["id"];
        $data["akun_akumulasi_id"]    = $data["akun_akumulasi"]["id"];
        $data["akun_beban_id"]    = $data["akun_beban"]["id"];
        $data["tanggal_beli"] = date("Y-m-d", strtotime($data["tanggal"]));
        $data["harga_beli"]   = $data["harga"];
        if ($data["is_penyusutan"]==1) {
            $data["umur_ekonomis"] = $data["umur"]["id"];
            $data["tahun"] = $data["umur"]["tahun"];
            $data["persentase"] = $data["umur"]["persentase"];
            $data["nilai_residu"] = $data["nilai_residu"];
        }
        $data["status"]       = "Aktif";
        // echo json_encode($data); die();
        if (isset($data["id"])) {
            $model = $sql->update("acc_asset", $data, array('id' => $data['id']));
        } else {
            $model = $sql->insert("acc_asset", $data);
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

$app->post('/acc/m_asset/save_pelepasan', function ($request, $response) {

    $params = $request->getParams();
    $data   = $params;
    $sql    = $this->db;
    if ($data["status"] == "Aktif") {
        unset($data["status"]);
    }

    $validasi = validasi_pelepasan($data);
    if ($validasi === true) {
        $data["tanggal_pelepasan"] = date("Y-m-d", strtotime($data["tanggal_pelepasan"]));

        $model = $sql->update("acc_asset", $data, array('id' => $data['id']));

        if ($model) {
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/m_asset/trash', function ($request, $response) {

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

    $model = $db->update("acc_asset", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->post('/acc/m_asset/delete', function ($request, $response) {
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

    $delete = $db->delete('m_asset', array('id' => $data['id']));
    if ($delete) {
        return successResponse($response, ['data berhasil dihapus']);
    } else {
        return unprocessResponse($response, ['data gagal dihapus']);
    }
});
