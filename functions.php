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
function modulUrl()
{
    return config('SITE_URL') . "/" . config('MODUL_ACC_PATH');
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
            $db->customWhere("acc_trans_detail.m_lokasi_id in (".implode(",", $lokasiId).")", "and");
        } else {
            $child   = getChildId("acc_m_lokasi", $lokasiId);
            $child[] = $lokasiId;
            $db->customWhere("acc_trans_detail.m_lokasi_id in (".implode(",", $child).")", "and");
        }
    }
    $model = $db->find();
    $debit = isset($model->debit) ? $model->debit : 0;
    $kredit = isset($model->kredit) ? $model->kredit : 0;
    return $debit - $kredit;
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
    }
    $data['saldo_awal'] = 0;
    $data['total_saldo'] = 0;
    /*
     * get akun parent 0, akun utama
     */
    $klasifikasi = $sql->select("*")
            ->from("acc_m_akun")
            ->customWhere("tipe IN ('PENDAPATAN', 'BIAYA', 'BEBAN')")
            ->where("is_tipe", "=", 1)
            ->where("level", "=", 1)
            ->findAll();
    $arr = [];
    $total = 0;
    
    /*
     * deklarasi total per tipe
     */
    $total_ = [];
    foreach($klasifikasi as $key => $val){
        $total_[$val->tipe] = 0;
    }
//    print_r($total);die();
    /*
     * ambil akun pengecualian
     */
    $akunPengecualian = getMasterSetting();
    $arrPengecualian = [];
    foreach($akunPengecualian->pengecualian_labarugi as $a => $b){
        array_push($arrPengecualian, $b->m_akun_id->id);
    }
    
    /*
     * proses perulangan
     */
    foreach ($klasifikasi as $index => $akun) {
        $arr[$index] = (array) $akun;
        $arr[$index]['total'] = 0;
        /*
         * ambil child akun
         */
        $akunId = getChildId("acc_m_akun", $akun->id);
        foreach($arrPengecualian as $w => $x){
            foreach($akunId as $y => $z){
                if($z == $x)
                    unset ($akunId[$y]);
            }
        }
        $getakun = $sql->select("*")
                ->from("acc_m_akun")
                ->customWhere("id IN(" . implode(',', $akunId) . ")")
                ->orderBy("kode")
                ->findAll();
        foreach ($getakun as $key => $val) {
            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail");
            if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
                $sql->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
            }
            $sql->where('acc_trans_detail.m_akun_id', '=', $val->id);
            if ($tanggal_end != null) {
                $sql->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
            } else {
                $sql->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_start);
            }
            $gettransdetail = $sql->find();
            if ((intval($gettransdetail->debit) - intval($gettransdetail->kredit) > 0) || (intval($gettransdetail->debit) - intval($gettransdetail->kredit) < 0) || $val->is_tipe == 1) {
                if ($val->is_tipe == 1) {
                    $arr[$index]['detail'][$val->id]['kode'] = $val->kode;
                    $arr[$index]['detail'][$val->id]['nama'] = $val->nama;
                    $arr[$index]['detail'][$val->id]['nominal'] = 0;
                } else {
                    $arr[$index]['detail'][$val->parent_id]['detail'][$key]['kode'] = $val->kode;
                    $arr[$index]['detail'][$val->parent_id]['detail'][$key]['nama'] = $val->nama;
                    $arr[$index]['detail'][$val->parent_id]['detail'][$key]['nominal'] = (intval($gettransdetail->debit) - intval($gettransdetail->kredit)) * $val->saldo_normal;
                    $arr[$index]['total'] += $arr[$index]['detail'][$val->parent_id]['detail'][$key]['nominal'];
                    $arr[$index]['detail'][$val->parent_id]['nominal'] += $arr[$index]['detail'][$val->parent_id]['detail'][$key]['nominal'];
                    
                    $total_[$val->tipe] += $arr[$index]['total'];
                }
                if ($akun->tipe == "HARTA" || $akun->tipe == "PENDAPATAN LAIN") {
                    $total += (intval($gettransdetail->debit) - intval($gettransdetail->kredit)) * $val->saldo_normal;
                } else {
                    $total -= (intval($gettransdetail->debit) - intval($gettransdetail->kredit));
                }
            }
        }
    }
    
//    print_r($total_);die();
    
    if ($array) {
        return ["data"=>$arr, "total"=>$total_];
    } else {
        return $total;
    }
}

function getPemetaanAkun($type){
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $akun = $db->select("*")->from("acc_m_akun_peta")->where("type", "=", $type)->find();
    return $akun->m_akun_id;
}

function getMasterSetting(){
    $db   = new Cahkampung\Landadb(config('DB')['db']);
    $data = $db->select("*")
            ->from("acc_m_setting")
            ->find();
    
    $data->pengecualian_neraca = json_decode($data->pengecualian_neraca);
    $data->pengecualian_labarugi = json_decode($data->pengecualian_labarugi);
    
    return $data;
}
