<?php
date_default_timezone_set('Asia/Jakarta');
function validasi($data, $custom = array())
{
    $validasi = array(
        'supplier' => 'required',
        'akun_hutang' => 'required',
        'tanggal' => 'required',
        'total' => 'required',
    );
    $cek = validate($data, $validasi, $custom);
    return $cek;
}
$app->get('/acc/t_saldo_awal_hutang_persupplier/index', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $db->select("
            acc_saldo_hutang.*,
            acc_m_kontak.kode as kodeSup,
            acc_m_kontak.nama as namaSup,
            acc_m_kontak.tlp as tlpSup,
            acc_m_kontak.email as emailSup,
            acc_m_kontak.alamat as alamatSup,
            hutang.kode as kodeAkunHutang,
            hutang.nama as namaAkunHutang,
            acc_m_lokasi.kode as kodeLokasi,
            acc_m_lokasi.nama as namaLokasi
        ")
            ->from('acc_saldo_hutang')
            ->leftJoin('acc_m_kontak', 'acc_m_kontak.id = acc_saldo_hutang.m_kontak_id')
            ->leftJoin('acc_m_akun hutang', 'hutang.id = acc_saldo_hutang.m_akun_hutang_id')
            ->leftJoin('acc_m_lokasi', 'acc_m_lokasi.id = acc_saldo_hutang.m_lokasi_id')
            ->orderBy('acc_saldo_hutang.tanggal DESC');
    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_saldo_hutang.is_deleted", '=', $val);
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
    if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
        $db->where("acc_saldo_hutang.m_lokasi_id", "=", $params['m_lokasi_id']);
    }
    $models = $db->findAll();
    $totalItem = $db->count();
    foreach ($models as $key => $val) {
        $val->supplier = ['nama' => $val->namaSup, 'id' => $val->m_kontak_id, 'kode' => $val->kodeSup, 'tlp' => $val->tlpSup, 'email' => $val->emailSup, 'alamat' => $val->alamatSup];
        $val->akun_hutang = ['nama' => $val->namaAkunHutang, 'id' => $val->m_akun_hutang_id, 'kode' => $val->kodeAkunHutang];
        $val->lokasi = ['nama' => $val->namaLokasi, 'id' => $val->m_lokasi_id, 'kode' => $val->kodeLokasi];
        $val->tanggal_formated = date("d-m-Y", strtotime($val->tanggal));
        $val->jatuh_tempo_formated = date("d-m-Y", strtotime($val->jatuh_tempo));
        $val->status = ucfirst($val->status);
    }
    return successResponse($response, [
        'list' => $models,
        'totalItems' => $totalItem,
        'base_url' => str_replace('api/', '', config('SITE_URL'))
    ]);
});
$app->post('/acc/t_saldo_awal_hutang_persupplier/save', function ($request, $response) {
    $params = $request->getParams();
    $data = $params;
    $sql = $this->db;
    $validasi = validasi($data);
    if ($validasi === true) {
        /*
         * kode
         */
        $kode = generateNoTransaksi("saldo_hutang", 0);
        $insert['m_kontak_id'] = $data['supplier']['id'];
        $insert['tanggal'] = date("Y-m-d", strtotime($data['tanggal']));
        $insert['m_lokasi_id'] = $data['lokasi']['id'];
        $insert['total'] = $data['total'];
        $insert['jatuh_tempo'] = date("Y-m-d", strtotime($data['tanggal']));
        $insert['status_hutang'] = 'belum lunas';
        $insert['status'] = $data['status'];
        $insert['m_akun_id'] = $data['akun_debit']['id'];
        $insert['m_akun_hutang_id'] = $data['akun_hutang']['id'];
        $insert['no_invoice'] = isset($data['no_invoice']) && !empty($data['no_invoice']) ? $data['no_invoice'] : null;
        $insert['keterangan'] = (isset($data['keterangan']) && !empty($data['keterangan']) ? $data['keterangan'] : '');
        if (isset($data['id']) && !empty($data['id'])) {
            $insert['kode'] = $data['kode'];
            $model = $sql->update("acc_saldo_hutang", $insert, ["id" => $data['id']]);
        } else {
            $insert['kode'] = $kode;
            $model = $sql->insert("acc_saldo_hutang", $insert);
        }
        $deletetransdetail = $sql->delete("acc_trans_detail", ["reff_id" => $model->id, "reff_type" => "acc_saldo_hutang"]);
        /*
         * deklarasi untuk simpan ke transdetail
         */
        $transDetail = [];
        /**
         * Simpan detail modal
         */
        $modal = getPemetaanAkun("Modal");
        $akunmodal = isset($modal[0]) ? $modal[0] : 0;
        if($akunmodal == 0){
             return unprocessResponse($response, ['Harap setting akun modal di menu Pemetaan Akun terlebih dahulu']);
        }
        $transDetail[0]['m_kontak_id'] = $model->m_kontak_id;
        $transDetail[0]['m_lokasi_id'] = $data['lokasi']['id'];
        $transDetail[0]['m_lokasi_jurnal_id'] = $data['lokasi']['id'];
        $transDetail[0]['m_akun_id'] = $akunmodal;
        $transDetail[0]['tanggal'] = date("Y-m-d", strtotime($data['tanggal']));
        $transDetail[0]['debit'] = $data['total'];
        $transDetail[0]['reff_type'] = "acc_saldo_hutang";
        $transDetail[0]['reff_id'] = $model->id;
        $transDetail[0]['kode'] = $model->kode;
        $transDetail[0]['keterangan'] = (isset($data['keterangan']) && !empty($data['keterangan']) ? $data['keterangan'] : '');
        /**
         * Simpan detail hutang
         */
        $transDetail[1]['m_kontak_id'] = $model->m_kontak_id;
        $transDetail[1]['m_lokasi_id'] = $data['lokasi']['id'];
        $transDetail[1]['m_lokasi_jurnal_id'] = $data['lokasi']['id'];
        $transDetail[1]['m_akun_id'] = $data['akun_hutang']['id'];
        $transDetail[1]['tanggal'] = date("Y-m-d", strtotime($data['tanggal']));
        $transDetail[1]['kredit'] = $data['total'];
        $transDetail[1]['reff_type'] = "acc_saldo_hutang";
        $transDetail[1]['reff_id'] = $model->id;
        $transDetail[1]['keterangan'] = (isset($data['keterangan']) && !empty($data['keterangan']) ? $data['keterangan'] : '');
        $transDetail[1]['kode'] = $model->kode;
        /*
         * Simpan array trans detail ke database jika simpan dan kunci
         */
        if ($data['status'] == "terposting") {
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
$app->post('/acc/t_saldo_awal_hutang_persupplier/delete', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $model = $db->delete("acc_saldo_hutang", ['id' => $data['id']]);
    $model2 = $db->delete("acc_trans_detail", ["reff_type" => "acc_saldo_hutang", "reff_id" => $data['id']]);
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
