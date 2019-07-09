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
function buildFlatTree($tree, $ids = [])
{
    $colName = 'id';
    $childColName = 'children';
    foreach ($tree as $element) {
        if (!isset($element->$colName)) {
            continue;
        }
        $ids[] = $element->$colName;

        if (isset($element->$childColName) && count($element->$childColName) > 0) {
            $ids = buildFlatTree($element->$childColName, $ids);
        }
    }
    return $ids;
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
    $child = buildFlatTree($tree);

    return $child;
}

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
            ->customWhere("id IN (4, 5, 6, 7, 8, 9)")
            ->findAll();
    $arr = [];
    $total = 0;

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
                }

                if($akun->tipe == "HARTA" || $akun->tipe == "PENDAPATAN LAIN"){
                    $total += (intval($gettransdetail->debit) - intval($gettransdetail->kredit)) * $val->saldo_normal;
                }else{
                    $total -= (intval($gettransdetail->debit) - intval($gettransdetail->kredit));
                }
            }
        }
    }

    if ($array) {
        return $arr;
    } else {
        return $total;
    }
}
