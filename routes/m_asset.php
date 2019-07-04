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
        'jenis_pelepasan' => 'required',
        'tgl_pelepasan' => 'required',
    );
    GUMP::set_field_name("jenis_pelepasan", "Jenis Pelepasan");
    GUMP::set_field_name("tgl_pelepasan", "Tanggal Pelepasan");
    GUMP::set_field_name("nilai_pelepasan", "Nilai Pelepasan");
    GUMP::set_field_name("akun_kas_pelepasan", "Akun Kas / Piutang");
    GUMP::set_field_name("akun_laba_rugi", "Akun Laba / Rugi");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/m_asset/tampilPenyusutan', function ($request, $response) {
    $params = $request->getParams();


    $sql = $this->db;
    $sql->select("*")->from("acc_asset")
        ->where("status", "=", 'Aktif')
        ->where("is_penyusutan", "=", 1)
        ->where("lokasi_id", "=", $params['lokasi_id'])
        ->where("periode_awal_penyusutan", "<", date("Y-m-t",strtotime($params['bulan'])))
        ->where("periode_akhir_penyusutan", ">", date("Y-m-t",strtotime($params['bulan'])))
        ;
    $total = 0;
    $models = $sql->findAll();
    foreach ($models as $key => $value) {
        $total += $value->penyusutan_perbulan;
    }

    return successResponse($response, [
        'list'     => $models,
        'total'    => $total,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
    ]);
});

$app->post('/acc/m_asset/prosesPenyusutan', function ($request, $response) {

    $params = $request->getParams();
    $data   = $params;
    $sql    = $this->db;

    //cek riwayat penyusutan jika ada maka ditimpa
    $cek_riw = $sql->select("acc_riw_penyusutan.id,acc_jurnal.id as jurnal_id")
                ->from("acc_riw_penyusutan")
                ->leftJoin("acc_jurnal","acc_jurnal.riw_penyusutan_id = acc_riw_penyusutan.id")
                ->where("acc_riw_penyusutan.periode","=",date("Y-m-d",strtotime($params["bulan"])))
                ->where("acc_riw_penyusutan.lokasi_id","=",$params["form"]["lokasi"]["id"])
                ->find();
    if ($cek_riw!=FALSE) {
        $delete = $sql->delete('acc_riw_penyusutan', array('id' => $cek_riw->id));
        $delete = $sql->delete('acc_riw_penyusutan_dt', array('riw_id' => $cek_riw->id));
        $delete = $sql->delete('acc_jurnal', array('riw_penyusutan_id' => $cek_riw->id));
        $delete = $sql->delete('acc_jurnal_det', array('acc_jurnal_id' => $cek_riw->jurnal_id));
        $delete = $sql->delete('acc_trans_detail', array('reff_type' => "acc_jurnal_penyusutan",'reff_id' => $cek_riw->jurnal_id));
    }

    //insert riwayat penyusutan
    $d_riw = array(
        "periode" => date("Y-m-d",strtotime($params["bulan"])),
        "lokasi_id" => $params["form"]["lokasi"]["id"]        
    );
    $insert_riw = $sql->insert("acc_riw_penyusutan", $d_riw);

    //insert jurnal
    $cek = $sql->find("select no_transaksi from acc_jurnal order by no_transaksi desc");
    $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -4)) + 1;
    $no_urut = substr('0000' . $urut, -4);
    $no_transaksi = $params["form"]["lokasi"]["kode"].date("y"). "JRNL" . $no_urut;
    $keterangan = "Penyusutan ".$params["form"]["lokasi"]["nama"]." Bulan ".date("F Y",strtotime($params["bulan"]));

    $d_jurnal = array(
        "no_transaksi" => $no_transaksi,
        "m_lokasi_id" => $params["form"]["lokasi"]["id"],
        "keterangan" => $keterangan,
        "tanggal" => date("Y-m-d",strtotime($params["bulan"])),
        "total_kredit" =>  $params["form"]["total"],
        "total_debit" =>  $params["form"]["total"],
        "riw_penyusutan_id" => $insert_riw->id
    );
    $insert_jurnal = $sql->insert("acc_jurnal", $d_jurnal);


    foreach ($params["listDetail"] as $key => $value) {
        $keterangan_det = "Penyusutan ".$value["nama"]." (".$value["no_serial"].") Bulan ".date("F Y",strtotime($params["bulan"]));
        //insert dt riwayat penyusutan
        $dt_riw = array(
            "riw_id" => $insert_riw->id,
            "asset_id" => $value["id"],
            "penyusutan_perbulan" => $value["penyusutan_perbulan"]
        );
        $insert_riw_dt = $sql->insert("acc_riw_penyusutan_dt", $dt_riw);

        //insert jurnal umum debit
        $dt_jurnal_deb = array(
            "acc_jurnal_id" => $insert_jurnal->id,
            "m_akun_id" => $value["akun_beban_id"],
            "m_lokasi_id" => $value["lokasi_id"],
            "debit" => $value["penyusutan_perbulan"],
            "kredit" => 0,
            "keterangan" => $keterangan_det
        );
        $insert_jurnal_dt1 = $sql->insert("acc_jurnal_det", $dt_jurnal_deb);

        //insert jurnal umum kredit
        $dt_jurnal_kredit = array(
            "acc_jurnal_id" => $insert_jurnal->id,
            "m_akun_id" => $value["akun_akumulasi_id"],
            "m_lokasi_id" => $value["lokasi_id"],
            "debit" => 0,
            "kredit" => $value["penyusutan_perbulan"],
            "keterangan" => $keterangan_det
        );
        $insert_jurnal_dt2 = $sql->insert("acc_jurnal_det", $dt_jurnal_kredit);

        //insert transdt umum debit
        $transdet_deb = array(
            "reff_id" => $insert_jurnal->id,
            "reff_type" => "acc_jurnal_penyusutan",
            "m_akun_id" => $value["akun_beban_id"],
            "m_lokasi_id" => $value["lokasi_id"],
            "debit" => $value["penyusutan_perbulan"],
            "kredit" => 0,
            "keterangan" => $keterangan_det,
            "tanggal" => date("Y-m-d",strtotime($params["bulan"])),
            "kode" => $no_transaksi
        );
        $insert_trans1 = $sql->insert("acc_trans_detail", $transdet_deb);

        //insert transdt umum kredit
        $transdet_kredit = array(
            "reff_id" => $insert_jurnal->id,
            "reff_type" => "acc_jurnal_penyusutan",
            "m_akun_id" => $value["akun_akumulasi_id"],
            "m_lokasi_id" => $value["lokasi_id"],
            "debit" => 0,
            "kredit" => $value["penyusutan_perbulan"],
            "keterangan" => $keterangan_det,
            "tanggal" => date("Y-m-d",strtotime($params["bulan"])),
            "kode" => $no_transaksi
        );
        $insert_trans2 = $sql->insert("acc_trans_detail", $transdet_kredit);

    };

    if ($insert_jurnal) {
        return successResponse($response, $insert_jurnal);
    } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
    }
});

$app->get('/acc/m_asset/getDetailPenyusutan', function ($request, $response) {
    $params = $request->getParams();

    $sql = $this->db;
    $sql->select("*")->from("acc_asset")
        ->where("id", "=", $params["id"]);
    $models = $sql->find();

    $tahun = date("Y",strtotime($models->tanggal_beli));
    
    if ($models->status=='Aktif') {
        $batas_tahun = $tahun + $models->tahun;
        $batas_bulan = date('m', strtotime('-1 months', strtotime($models->tanggal_beli)));
    }else{
        $batas_tahun = date("Y",strtotime($models->tgl_pelepasan));
        $batas_bulan = date('m', strtotime($models->tgl_pelepasan));
    }

    $dt = []; 
    $data_update = [];
    for ($i=$tahun; $i <= $batas_tahun ; $i++) {
        if ($i==$tahun) {
            $dt[$i]['saldo_awal'] = $models->harga_beli;
            $dt[$i]['awal'] = date("t M Y",strtotime($models->tanggal_beli));
            $dt[$i]['akhir'] = date("t M Y", strtotime($i."-12-01"));

            //format
            $dt[$i]['awal_default'] = date("Y-m-t",strtotime($models->tanggal_beli));
            $dt[$i]['akhir_default'] = date("Y-m-t", strtotime($i."-12-01"));

            //data_update
            $data_update["periode_awal_penyusutan"] = $dt[$i]['awal_default']; 
        }else if ($i==$batas_tahun) {
            $dt[$i]['awal'] = date("t M Y",strtotime($i."-01-01"));
            $dt[$i]['akhir'] = date("t M Y",strtotime($i."-".$batas_bulan."-01")); 

            //format
            $dt[$i]['awal_default'] = date("Y-m-d",strtotime($i."-01-01"));
            $dt[$i]['akhir_default'] = date("Y-m-t",strtotime($i."-".$batas_bulan."-01")); 
            $dt[$i]['saldo_awal'] = $dt[$i-1]['saldo_akhir']; 

            //data_update
            $data_update["periode_akhir_penyusutan"] = $dt[$i]['akhir_default'];
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


        //data update
        $data_update["penyusutan_perbulan"] = round($dt[$i]['penyusutan_perbulan']);
    }

    $model = $sql->update("acc_asset", $data_update, array('id' => $params['id']));

    return successResponse($response, [
        'data_update' => $data_update,
        'list'     => $dt,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
    ]);
});
$app->get('/acc/m_asset/getAkun', function ($request, $response) {
    $params = $request->getParams();

    $sql = $this->db;
    $sql->select("*")->from("acc_m_akun")
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
    $db->select("acc_asset.*,acc_m_lokasi.nama as nm_lokasi,acc_m_lokasi.kode as kode_lokasi, acc_umur_ekonomis.nama as nama_umur, acc_umur_ekonomis.tahun as tahun_umur, acc_umur_ekonomis.persentase as persentase_umur, akun_asset.nama as nm_akun_asset, akun_akumulasi.nama as nm_akun_akumulasi, akun_beban.nama as nm_akun_beban")
        ->from("acc_asset")
        ->leftJoin("acc_m_lokasi", "acc_m_lokasi.id = acc_asset.lokasi_id")
        ->leftJoin("acc_umur_ekonomis", "acc_umur_ekonomis.id = acc_asset.umur_ekonomis")
        ->leftJoin("acc_m_akun akun_asset", "akun_asset.id = acc_asset.akun_asset_id")
        ->leftJoin("acc_m_akun akun_akumulasi", "akun_akumulasi.id = acc_asset.akun_akumulasi_id")
        ->leftJoin("acc_m_akun akun_beban", "akun_beban.id = acc_asset.akun_beban_id")
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
        if ($value->lokasi_id==-1) {
            $value->lokasi = ["id" => $value->lokasi_id, "nama" => 'Lainya'];
        }else{
            $value->lokasi = ["id" => $value->lokasi_id, "nama" => $value->nm_lokasi, "kode_lokasi" => $value->kode_lokasi];
        }
        $value->tanggal_beli_format = date("d-m-Y",strtotime($value->tanggal_beli));
        $value->umur = ["id" => $value->umur_ekonomis, "nama" => $value->nama_umur, "tahun" => $value->tahun_umur, "persentase" => $value->persentase_umur];
        $value->persentase = (Float) $value->persentase;
        $value->akun_asset = ["id"=>$value->akun_asset_id,"nama"=>$value->nm_akun_asset];
        $value->akun_akumulasi = ["id"=>$value->akun_akumulasi_id,"nama"=>$value->nm_akun_akumulasi];
        $value->akun_beban = ["id"=>$value->akun_beban_id,"nama"=>$value->nm_akun_beban];
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
        if ($data["lokasi"]["id"]==-1) {
            $data["nama_lokasi"] = $data["nama_lokasi"];
        }else{
            $data["nama_lokasi"] = $data["lokasi"]["nama"];
        }
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

$app->post('/acc/m_asset/proses_pelepasan', function ($request, $response) {

    $params = $request->getParams();
    $data   = $params;
    $sql    = $this->db;
    if ($data["jenis_pelepasan"] == "Dijual") {
        $validasi = validasi_pelepasan($data,["nilai_pelepasan"=>'required','akun_kas_pelepasan'=>'required','akun_laba_rugi'=>'required']);    
    }else{
        $validasi = validasi_pelepasan($data,['akun_laba_rugi'=>'required']);
    }

    
    if ($validasi === true) {
        $data["tgl_pelepasan"] = date("Y-m-d", strtotime($data["tgl_pelepasan"]));
        $data["status"] = $data["jenis_pelepasan"];
        if ($data["jenis_pelepasan"] == "Dijual") {
            $data["akun_kas_pelepasan_id"] = $data["akun_kas_pelepasan"]["id"]; 
        }
        $data["akun_laba_rugi_id"] = $data["akun_laba_rugi"]["id"]; 

        //update asset
        $model = $sql->update("acc_asset", $data, array('id' => $data['id']));
        
        //hitung akumulasi penyusutan sampai tgl pelepasan
        $datetime1 = new DateTime(date("Y-m-t",strtotime($data['tanggal_beli'])));
        $datetime2 = new DateTime(date("Y-m-d",strtotime($data['tgl_pelepasan'])));
        $interval = $datetime1->diff($datetime2);
        $selisih =  $interval->m + 1 + ($interval->y*12);
        $nilai_akumulasi = $data['penyusutan_perbulan']*$selisih;

        //jurnal
        $cek = $sql->find("select no_transaksi from acc_jurnal order by no_transaksi desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -4)) + 1;
        $no_urut = substr('0000' . $urut, -4);
        $no_transaksi = $data["lokasi"]["kode_lokasi"].date("y"). "JRNL" . $no_urut;
        $keterangan = "Pelepasan Asset ".$data["nama"];

        //insert jurnal & trans detail
        $dt_juranal = []; $total_kredit = 0; $total_debit = 0;
        if ($data['jenis_pelepasan']=='Dijual') {
            $hitung_laba_rugi = ($data["nilai_pelepasan"]+(int)($nilai_akumulasi)) - $data["harga_beli"];
            if ($hitung_laba_rugi<=0) {
                //rugi
                $total_kredit = $data["harga_beli"];  
                $total_debit = $data["nilai_pelepasan"] + $nilai_akumulasi+ abs($hitung_laba_rugi);
            }else{
                //laba
                $total_kredit = $data["harga_beli"] + $hitung_laba_rugi;  
                $total_debit = $data["nilai_pelepasan"] + $nilai_akumulasi;
            }
        }else{
            $rugi = abs($data["harga_beli"] - $nilai_akumulasi);
            $total_kredit = $data["harga_beli"];  
            $total_debit = $nilai_akumulasi + abs($rugi);
        }

        $d_jurnal = array(
                "no_transaksi" => $no_transaksi,
                "m_lokasi_id" => $data["lokasi"]["id"],
                "keterangan" => $keterangan,
                "tanggal" => $data["tgl_pelepasan"],
                "total_kredit" =>  $total_kredit,
                "total_debit" =>  $total_debit
            );
        $insert_jurnal = $sql->insert("acc_jurnal", $d_jurnal);

        if ($data['jenis_pelepasan']=='Dijual') {
            $hitung_laba_rugi = ($data["nilai_pelepasan"]+(int)($nilai_akumulasi)) - $data["harga_beli"];
            if ($hitung_laba_rugi<=0) {
                //rugi
                $dt_juranal["Kas Piutang"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_kas_pelepasan"]["id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => $data["nilai_pelepasan"],
                    "kredit" => 0,
                    "keterangan" => $keterangan.' (Kas/Piutang)'
                );

                $dt_juranal["Akumulasi"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_akumulasi_id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => $nilai_akumulasi,
                    "kredit" => 0,
                    "keterangan" => $keterangan.' (Akumulasi)'
                );

                $dt_juranal["Rugi"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_laba_rugi"]["id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => abs($hitung_laba_rugi),
                    "kredit" => 0,
                    "keterangan" => $keterangan.' (Rugi)'
                );

                $dt_juranal["Akun Asset"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_asset_id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => 0,
                    "kredit" => $data["harga_beli"],
                    "keterangan" => $keterangan.' (Akun Asset)'
                );

            }else{
                //laba
                $dt_juranal["Kas Piutang"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_kas_pelepasan"]["id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => $data["nilai_pelepasan"],
                    "kredit" => 0,
                    "keterangan" => $keterangan.' (Kas/Piutang)'
                );

                $dt_juranal["Akumulasi"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_akumulasi_id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => $nilai_akumulasi,
                    "kredit" => 0,
                    "keterangan" => $keterangan.' (Akumulasi)'
                );

                $dt_juranal["Laba"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_laba_rugi"]["id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => 0,
                    "kredit" => $hitung_laba_rugi,
                    "keterangan" => $keterangan.' (Laba)'
                );

                $dt_juranal["Akun Asset"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_asset_id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => 0,
                    "kredit" => $data["harga_beli"],
                    "keterangan" => $keterangan.' (Akun Asset)'
                );

                
            }

        }else{
            $rugi = abs($data["harga_beli"] - $nilai_akumulasi);
            $total_kredit = $data["harga_beli"];  
            $total_debit = $nilai_akumulasi + abs($rugi);

            $dt_juranal["Akumulasi"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_akumulasi_id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => $nilai_akumulasi,
                    "kredit" => 0,
                    "keterangan" => $keterangan.' (Akumulasi)'
            );

            $dt_juranal["Rugi"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_laba_rugi"]["id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => abs($rugi),
                    "kredit" => 0,
                    "keterangan" => $keterangan.' (Rugi)'
            );

            $dt_juranal["Akun Asset"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_asset_id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => 0,
                    "kredit" => $data["harga_beli"],
                    "keterangan" => $keterangan.' (Akun Asset)'
            );
        }

        foreach ($dt_juranal as $key => $value) {
                //insert dt_jurnal                
                $insert_dt_jurnal = $sql->insert("acc_jurnal_det", $dt_juranal[$key]);
                //insert transdt
                $dt_juranal[$key]["reff_id"] = $insert_jurnal->id;
                $dt_juranal[$key]["reff_type"] = "acc_jurnal_pelepasan";
                $dt_juranal[$key]["tanggal"] = $data["tgl_pelepasan"];
                $dt_juranal[$key]["kode"] = $no_transaksi;
                $insert_trans = $sql->insert("acc_trans_detail", $dt_juranal[$key]);
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
