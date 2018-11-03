<?php
namespace GJClasses;

class Export
{

    public function openFile($base_filename, $append_data)
    {
        header('Content-Encoding: UTF-8');
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"" . $base_filename . "_" . $append_data . ".csv\"");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header("Pragma: no-cache");

        $open_file = fopen('php://output', 'w');
        fprintf($open_file, chr(0xEF) . chr(0xBB) . chr(0xBF));

        return $open_file;
    }

    public function writeRow($open_file, $row_contents)
    {
        fputcsv($open_file, $row_contents);
    }

    public function writeBlankRow($open_file)
    {
        $blank_line = array('');
        fputcsv($open_file, $blank_line);
    }

    public function closeFile($open_file)
    {
        fclose($open_file);
        return exit;
    }

}
