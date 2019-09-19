<?php
/**
 * Multi insert ke trans detail
 */
function insertTransDetail($data)
{
    $db = new Cahkampung\Landadb(config('DB')['db']);
    if (!empty($data)) {
        foreach ($data as $key => $value) {
            $db->insert("acc_trans_detail", $value);
        }
    }
}
/**
 * Set modul ACC URL
 */
function modulUrl() {
    $port = !empty($_SERVER['SERVER_PORT']) ? ":".$_SERVER['SERVER_PORT'] : "";
    $a = "http://" . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
    $a = str_replace($_SERVER['PATH_INFO'], '', $a);
    $a = substr($a, 0, strpos($a, "?"));
//    echo $a;
//    echo config('SITE_URL');die;
    return $a . "/" . config('MODUL_ACC_PATH');
}
/**
 * Set path untuk slim twig view
 */
function twigViewPath()
{
    $view = new \Slim\Views\Twig(config('MODUL_ACC_PATH') . '/view');
    return $view;
}
function twigView()
{
    $view = new \Slim\Views\Twig('views');
    return $view;
}
/**
 * Buat nested tree
 */
function buildTree($elements, $parentId = 0)
{
    $branch = array();
    foreach ($elements as $element) {
        if ($element->parent_id == $parentId) {
            $children = buildTree($elements, $element->id);
            if ($children) {
                $element->children = $children;
            }
            $branch[$element->id] = $element;
        }
    }
    return $branch;
}
/**
 * ubah id child jadi numerical array
 */
function buildFlatTreeId($tree, $ids = [])
{
    $colName = 'id';
    $childColName = 'children';
    foreach ($tree as $element) {
        if (!isset($element->$colName)) {
            continue;
        }
        $ids[] = $element->$colName;
        if (isset($element->$childColName) && count($element->$childColName) > 0) {
            $ids = buildFlatTreeId($element->$childColName, $ids);
        }
    }
    return $ids;
}
/**
 * ubah child jadi flat array
 */
function flatten($arr)
{
    $result = [];
    foreach ($arr as $item) {
        $result[] = $item;
        if (isset($item->children)) {
            $result = array_merge($result, flatten($item->children));
        }
        unset($item->children);
    }
    return $result;
}
/**
 * Ambil semua child
 */
function getChildFlat($array, $parentId)
{
    $tree = buildTree($array, $parentId);
    $child = flatten($tree);
    return $child;
}
/**
 * Ambil semua id child
 */
function getChildId($tabelName, $parentId)
{
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $db->select("*")->from($tabelName)->where("is_deleted", "=", 0);
    $data = $db->findAll();
    $tree = buildTree($data, $parentId);
    $child = buildFlatTreeId($tree);
    return $child;
}
/**
 * Ambil saldo awal
 */
function getSaldo($akunId, $lokasiId, $tanggal)
{
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $db->select("sum(debit) as debit, sum(kredit) as kredit")
            ->from("acc_trans_detail")
            ->where("m_akun_id", "=", $akunId);
    if (!empty($lokasiId)) {
        if (is_array($lokasiId) && !empty($lokasiId)) {
            $db->customWhere("acc_trans_detail.m_lokasi_id in (" . implode(",", $lokasiId) . ")", "and");
        } else {
            $child = getChildId("acc_m_lokasi", $lokasiId);
            $child[] = $lokasiId;
            $db->customWhere("acc_trans_detail.m_lokasi_id in (" . implode(",", $child) . ")", "and");
        }
    }
    $model = $db->find();
    $debit = isset($model->debit) ? $model->debit : 0;
    $kredit = isset($model->kredit) ? $model->kredit : 0;
    return $debit - $kredit;
}
/**
 * Nominal Laba Rugi
 */
function getLabaRugiNominal($tglStart = null, $tglEnd = null, $lokasi = null)
{
    $sql = new Cahkampung\Landadb(config('DB')['db']);
    /*
     * ambil child lokasi
     */
    if (!empty($lokasi)) {
        $lokasiId = getChildId("acc_m_lokasi", $lokasi);
        if (!empty($lokasiId)) {
            array_push($lokasiId, $lokasi);
            $lokasiId = implode(",", $lokasiId);
        } else {
            $lokasiId = $lokasi;
        }
    }
    /**
     * Ambil transaksi
     */
    $sql->select("sum(acc_trans_detail.debit) as debit, sum(acc_trans_detail.kredit) as kredit, acc_m_akun.saldo_normal, acc_m_akun.tipe")
            ->from("acc_trans_detail")
            ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
            ->customWhere("acc_m_akun.tipe IN ('PENDAPATAN', 'BIAYA', 'BEBAN')")
            ->groupBy("acc_m_akun.id")
            ->findAll();
    /**
     * Set parameter lokasi
     */
    if (!empty($lokasi)) {
        $sql->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND");
    }
    /**
     * Set parameter tanggal
     */
    if (!empty($tglStart)) {
        $sql->andWhere("date(acc_trans_detail.tanggal)", ">=", $tglStart);
    }
    if (!empty($tglEnd)) {
        $sql->andWhere("date(acc_trans_detail.tanggal)", "<=", $tglEnd);
    }
    /**
     * hitung laba rugi
     */
    $model = $sql->findAll();
    $total = 0;
    foreach ($model as $key => $value) {
        $subTotal = intval($value->debit) - intval($value->kredit);
        if ($value->tipe == "PENDAPATAN") {
            $total += ($subTotal * $value->saldo_normal);
        } else {
            $total -= ($subTotal * $value->saldo_normal);
        }
    }
    /**
     * Ambil akun laba rugi
     */
    $pemetaan = getPemetaanAkun('Laba Rugi Berjalan');
    return [
        "m_akun_id" => $pemetaan,
        "total" => $total,
    ];
}
/**
 * Saldo Neraca
 */
function getSaldoNeraca($akunId, $lokasi, $tanggal)
{
    $sql = new Cahkampung\Landadb(config('DB')['db']);
    /*
     * ambil child lokasi
     */
    if (!empty($lokasi)) {
        $lokasiId = getChildId("acc_m_lokasi", $lokasi);
        if (!empty($lokasiId)) {
            array_push($lokasiId, $lokasi);
            $lokasiId = implode(",", $lokasiId);
        } else {
            $lokasiId = $lokasi;
        }
    }
    /**
     * Ambil transaksi
     */
    $sql->select("sum(acc_trans_detail.debit) as debit, sum(acc_trans_detail.kredit) as kredit, acc_m_akun.saldo_normal, acc_m_akun.id as m_akun_id")
            ->from("acc_trans_detail")
            ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
            ->customWhere("acc_m_akun.tipe IN ('HARTA', 'KEWAJIBAN', 'MODAL')")
            ->groupBy("acc_m_akun.id")
            ->findAll();
    /**
     * Set parameter lokasi
     */
    if (!empty($lokasi)) {
        $sql->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND");
    }
    /**
     * Set parameter Akun
     */
    if (!empty($akunId)) {
        if (is_array($akunId) && !empty($akunId)) {
            $sql->customWhere("acc_trans_detail.m_akun_id IN (" . implode(",", $akunId) . ")", "AND");
        } else {
            $sql->customWhere("acc_trans_detail.m_akun_id = '" . $akunId . "'", "AND");
        }
    }
    /**
     * Set parameter Tanggal
     */
    if (!empty($tanggal)) {
        $sql->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal);
    }
    $model = $sql->findAll();
    $arr = [];
    foreach ($model as $key => $value) {
        $subTotal = intval($value->debit) - intval($value->kredit);
        $arr[$value->m_akun_id] = $subTotal * $value->saldo_normal;
    }
    /**
     * Ambil laba rugi nominal
     */
    $labaRugi = getLabaRugiNominal(null, $tanggal, null);
    if (isset($arr[$labaRugi['m_akun_id']])) {
        $arr[$labaRugi['m_akun_id']] += $labaRugi['total'];
    } else {
        $arr[$labaRugi['m_akun_id']] = $labaRugi['total'];
    }
    return $arr;
}
/**
 * Laba rugi
 */
function getLabaRugi($tanggal_start, $tanggal_end = null, $lokasi = null, $array = true)
{
    $sql = new Cahkampung\Landadb(config('DB')['db']);
    /*
     * ambil child lokasi
     */
    if ($lokasi != null) {
        $lokasiId = getChildId("acc_m_lokasi", $lokasi);
        if (!empty($lokasiId)) {
            array_push($lokasiId, $lokasi);
            $lokasiId = implode(",", $lokasiId);
        } else {
            $lokasiId = $lokasi;
        }
    }else{
        $lokasi = $sql->findAll("select id from acc_m_lokasi where is_deleted = 0");
        $arrLok = [];
        foreach ($lokasi as $key => $value) {
            $arrLok[] = $value->id;
        }
        $lokasiId = implode(",", $arrLok);
    }
    $data['saldo_awal'] = 0;
    $data['total_saldo'] = 0;
    /*
     * ambil akun pengecualian
     */
    $akunPengecualian   = getMasterSetting();
    $arrPengecualian    = [];
    if (is_array($akunPengecualian) && !empty($akunPengecualian)) {
        foreach ($akunPengecualian->pengecualian_labarugi as $a => $b) {
            array_push($arrPengecualian, $b->m_akun_id->id);
        }
    }
    /**
     * Ambil transaksi di akun
     */
    $sql->select("
            SUM(debit) as debit, 
            SUM(kredit) as kredit,
            acc_m_akun.id,
            acc_m_akun.saldo_normal
        ")
        ->from("acc_trans_detail")
        ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
        ->customWhere("acc_m_akun.tipe in ('PENDAPATAN', 'BEBAN')")
        ->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND")
        ->groupBy("acc_m_akun.id");
    /**
     * Filter tanggal
     */
    if ($tanggal_end != null) {
        $sql->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
    } else {
        $sql->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_start);
    }
    /**
     * Filter pengecualian
     */
    if(!empty($arrPengecualian)){
        $sql->customWhere("m_akun_id NOT INT (".implode(",", $arrPengecualian).")", "And");
    }
    $trans = $sql->findAll();
    $arrTrans = [];
    foreach ($trans as $key => $value) {
        $arrTrans[$value->id] = (intval($value->debit) - intval($value->kredit)) * $value->saldo_normal;
    }
    /*
     * ambil akun (jika saldo 0 ikut ditampilkan)
     */
    $sql->select("id, nama, kode, tipe")
        ->from("acc_m_akun")
        ->customWhere("tipe in ('PENDAPATAN', 'BEBAN')")
        ->andWhere("is_deleted", "=", 0);
    $model = $sql->findAll();
    $grandTotal = ['PENDAPATAN' => 0, 'BEBAN' => 0];
    $arr        = [];
    foreach ($model as $key => $value) {
        $total = (isset($arrTrans[$value->id]) ? $arrTrans[$value->id] : 0);
        if (!empty($value->tipe) && $total != 0) {
            $grandTotal[$value->tipe] += $total;

            $arr[$value->tipe]['nama'] = $value->tipe;
            $arr[$value->tipe]['detail'][$value->id]['kode'] = $value->kode;
            $arr[$value->tipe]['detail'][$value->id]['nama'] = $value->nama;
            $arr[$value->tipe]['detail'][$value->id]['nominal'] = $total;
            $arr[$value->tipe]['total'] = (isset($arr[$value->tipe]['total']) ? $arr[$value->tipe]['total'] : 0) + $total;
        }
    }
    if ($array) {
        return ["data" => $arr, "total" => $grandTotal];
    } else {
        return $grandTotal['PENDAPATAN'] - $grandTotal['BEBAN'];
    }
}
function getPemetaanAkun($tipe = '')
{
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $arrAkun = [0 => 0];
    $db->select("*")
            ->from("acc_m_akun_peta");
    if (!empty($tipe)) {
        $db->where("type", "=", $tipe);
    }
    $akun = $db->findAll();
    foreach ($akun as $key => $value) {
        if (isset($value->is_multiple) && $value->is_multiple == 1) {
            $arrAkun[$value->type] = json_decode($value->m_akun_id);
        } else {
            $arrAkun[$value->type] = [0 => $value->m_akun_id];
        }
    }
    if (!empty($tipe)) {
        return isset($arrAkun[$tipe]) ? $arrAkun[$tipe] : 0;
    } else {
        return $arrAkun;
    }
}
function getMasterSetting()
{
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $data = $db->select("*")
            ->from("acc_m_setting")
            ->find();
    $data->pengecualian_neraca = json_decode($data->pengecualian_neraca);
    $data->pengecualian_labarugi = json_decode($data->pengecualian_labarugi);
    return $data;
}
function getSessionLokasi()
{
    $cabang = [];
    foreach ($_SESSION['user']['lokasi'] as $val) {
        $cabang[] = $val->id;
    }
    $return = implode(",", $cabang);
    return $return;
}
function generateNoTransaksi($type, $unker)
{
    $db = config('DB');
    $db = new Cahkampung\Landadb($db['db']);
    $custom = getMasterSetting();
    if ($type == 'penerimaan') {
        $cek = $db->find("select no_transaksi from acc_pemasukan order by no_transaksi desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = $custom->format_pemasukan;
        $no_transaksi = str_replace("TAHUN", date("y"), $no_transaksi);
        $no_transaksi = str_replace("BULAN", date("m"), $no_transaksi);
        $no_transaksi = str_replace("KODEPRODI", $unker, $no_transaksi);
        $no_transaksi = str_replace("NOURUT", $no_urut, $no_transaksi);
    } elseif ($type == 'pengeluaran') {
        $cek = $db->find("select no_urut, no_transaksi from acc_pengeluaran order by no_urut desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = $custom->format_pengeluaran;
        $no_transaksi = str_replace("TAHUN", date("y"), $no_transaksi);
        $no_transaksi = str_replace("BULAN", date("m"), $no_transaksi);
        $no_transaksi = str_replace("KODEPRODI", $unker, $no_transaksi);
        $no_transaksi = str_replace("NOURUT", $no_urut, $no_transaksi);
    } elseif ($type == 'transfer') {
        $cek = $db->find("select no_transaksi from acc_transfer order by no_transaksi desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = $custom->format_transfer;
        $no_transaksi = str_replace("TAHUN", date("y"), $no_transaksi);
        $no_transaksi = str_replace("BULAN", date("m"), $no_transaksi);
        $no_transaksi = str_replace("KODEPRODI", $unker, $no_transaksi);
        $no_transaksi = str_replace("NOURUT", $no_urut, $no_transaksi);
    } elseif ($type == 'jurnal') {
        $cek = $db->find("select no_transaksi from acc_jurnal order by no_transaksi desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = $custom->format_jurnal;
        $no_transaksi = str_replace("TAHUN", date("y"), $no_transaksi);
        $no_transaksi = str_replace("BULAN", date("m"), $no_transaksi);
        $no_transaksi = str_replace("KODEPRODI", $unker, $no_transaksi);
        $no_transaksi = str_replace("NOURUT", $no_urut, $no_transaksi);
    } elseif ($type == 'pengajuan') {
        $cek = $db->find("select no_proposal, no_urut from acc_t_pengajuan order by no_urut desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_proposal, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = $custom->format_pengajuan;
        $no_transaksi = str_replace("TAHUN", date("y"), $no_transaksi);
        $no_transaksi = str_replace("BULAN", date("m"), $no_transaksi);
        $no_transaksi = str_replace("KODEPRODI", $unker, $no_transaksi);
        $no_transaksi = str_replace("NOURUT", $no_urut, $no_transaksi);
    } elseif ($type == 'kasbon') {
        $cek = $db->find("select no_transaksi, no_urut from acc_kasbon order by no_urut desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = date("Y") . "/" . date("m") . "/KSBN/" . $no_urut;
    } elseif ($type == 'pembayaran_kasbon') {
        $cek = $db->find("select no_transaksi, no_urut from acc_bayar_kasbon order by no_urut desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = date("Y") . "/" . date("m") . "/" . $unker . "/BYRKSBN/" . $no_urut;
    } elseif ($type == 'pembayaran_hutang') {
        $cek = $db->find("select kode from acc_bayar_hutang order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "BS/" . date("Y") . "/". $no_urut;
    } elseif ($type == 'customer') {
        $cek = $db->find("select kode from acc_m_kontak where type = 'customer' order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "CUST" . date("Y") . "" . $no_urut;
    } elseif ($type == 'supplier') {
        $cek = $db->find("select kode from acc_m_kontak where type = 'supplier' order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "VND" . date("Y") . "" . $no_urut;
    } elseif ($type == 'stok_masuk') {
        $cek = $db->find("select kode from inv_stok_masuk order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "PI/" . date("m") . "/" . date("Y") . "/" . $no_urut;
    }
    return @$no_transaksi;
}
function tableUser()
{
    if (config('TABLE_USER') == "") {
        return "acc_m_user";
    } else {
        return config('TABLE_USER');
    }
}
