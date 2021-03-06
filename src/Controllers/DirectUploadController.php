<?php

namespace ArtinCMS\LFM\Controllers;

use ArtinCMS\LFM\Helpers\Classes\Media;
use ArtinCMS\LFM\Models\FileMimeType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DirectUploadController extends Controller
{
    public function directUpload($section = false, $callback)
    {
        $result = LFM_GetSection($section)['options'];
        $category_id = -5 ;
        if ($result)
        {
            $options = $result;
        }
        else
        {
            $options = [];
        }
        return view('laravel_file_manager::upload.upload_form', compact('category_id', 'callback', 'section', 'options'));
    }

    public function downloadDirect($id = -1, $default_img = '404.png', $quality = 100, $width = false, $height = false)
    {
        if ($id == -1)
        {
            return Media::downloadDirectById(-1, 'original', $default_img);//"Not Valid Request";
        }
        else
        {
            return Media::downloadDirectById($id, $default_img, false, $quality, $width, $height);
        }
    }

    public function storeDirectUploads(Request $request)
    {
        if ($request->file)
        {
            $CategoryID = $request->category_id;
            $result = [];
            $data = [];
            foreach ($request->file as $file)
            {
                try
                {
                    $mimeType = $file->getMimeType();
                    $FileMimeType = FileMimeType::where('mimeType', '=', $mimeType)->first();
                    $original_name = $file->getClientOriginalName();
                } catch (Exception $e)
                {
                    return $e->getMessage();
                }
                if (in_array($mimeType, config('laravel_file_manager.allowed')) === true && $FileMimeType)
                {
                    $section = LFM_GetSection($request->section);
                    if ($section)
                    {
                        if (isset($section['options']['path']))
                        {
                            $path = $section['options']['path'];
                            if (LFM_CheckAllowInsert($request->section)['available'] > 0)
                            {
                                $result[] = \DB::transaction(function () use ($file, $path, $FileMimeType) {
                                    $res = Media::directUpload($file, $path, $FileMimeType);
                                    $result['success'] = true;
                                    $result['file'] = $res;
                                    $result['full_url'] = LFM_GenerateDownloadLink('ID', $res['id'], 'original');
                                    $result['full_url_medium'] = LFM_GenerateDownloadLink('ID', $res['id'], 'original', '404.png', 100, 170, 120);
                                    $result['full_url_large'] = LFM_GenerateDownloadLink('ID', $res['id'], 'original');
                                    return $result;
                                });
                            }
                            else
                            {
                                $result[] = ['successs' => false, 'original_name' => $original_name, 'error' => 'You Reach Maximum Upload'];
                            }
                        }
                        else
                        {
                            $data[$request->section]['data'][] = ['successs' => false, 'original_name' => $original_name, 'error' => 'Your Upload Path not define'];
                            $data[$request->section]['available'] = LFM_CheckAllowInsert($request->section)['available'];
                            $data[$request->section]['view'] = ['list' => '', 'grid' => '', 'large' => '', 'medium' => '', 'small' => ''];
                            return response()->json($data);
                        }
                    }
                    else
                    {
                        $data[$request->section]['data'][] = ['successs' => false, 'original_name' => $original_name, 'error' => 'Your Section Not Define'];
                        $data[$request->section]['available'] = LFM_CheckAllowInsert($request->section)['available'];
                        $data[$request->section]['view'] = ['list' => '', 'grid' => '', 'large' => '', 'medium' => '', 'small' => ''];
                        return response()->json($data);
                    }
                }
                else
                {
                    $result[] = ['successs' => false, 'original_name' => $original_name, 'error' => 'Your Myme Type Is Not Allowed'];

                }
            }
            LFM_SetSelectedFileToSession($request, $request->section, $result);
            $data[$request->section]['available'] = LFM_CheckAllowInsert($request->section)['available'];
            if(!isset($section['options']['response_types']))
            {
                $data[$request->section]['data'] = $result;
                $data[$request->section]['view'] = LFM_GetSection($request->section)['selected']['view'];
            }
            else
            {
                $respnse_types = $section['options']['response_types'] ;
                if ($respnse_types)
                {
                    foreach ($respnse_types as $respnse_type)
                    {
                        switch ($respnse_type) {
                            case "json":
                                $data[$request->section]['data'] = $result;
                                break;
                            case "list_html":
                                $data[$request->section]['view']['list'] = LFM_GetSection($request->section)['selected']['view']['list'];
                                break;
                            case "medium_html":
                                $data[$request->section]['view']['medium'] = LFM_GetSection($request->section)['selected']['view']['medium'];
                                break;
                            case "large_html":
                                $data[$request->section]['view']['large'] = LFM_GetSection($request->section)['selected']['view']['large'];
                                break;
                            case "grid_html":
                                $data[$request->section]['view']['grid'] = LFM_GetSection($request->section)['selected']['view']['grid'];
                                break;
                            case "view":
                                $data[$request->section]['view'] = LFM_GetSection($request->section)['selected']['view'];
                                break;
                            case "green":
                        }
                    }
                }
            }

            return response()->json($data);
        }
    }
}
