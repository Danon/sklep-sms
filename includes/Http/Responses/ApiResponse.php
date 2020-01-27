<?php
namespace App\Http\Responses;

use Symfony\Component\HttpFoundation\Response;

class ApiResponse extends Response
{
    public function __construct($id, $text = "", $positive = false, $data = [], $status = 200)
    {
        $output['return_id'] = $id;
        $output['text'] = $text;
        $output['positive'] = $positive;

        if (is_array($data) && !empty($data)) {
            $output = array_merge($output, $data);
        }

        parent::__construct(json_encode($output), $status, [
            "Expires" => "Sat, 1 Jan 2000 01:00:00 GMT",
            "Last-Modified" => gmdate("D, d M Y H:i:s") . " GMT",
            "Cache-Control" => "no-cache, must-revalidate",
            "Pragma" => "no-cache",
            // It must be text/plain because of the way JS handles it
            "Content-Type" => "text/plain; charset=\"UTF-8\"",
        ]);
    }
}
