<?php

namespace App\Transformers\Common;

use App\Transformers\Transformer;
use App\Models\Master\BannerImage;
use App\Models\Admin\BannerImageModel;

class BannerImageTransformer extends Transformer
{
    protected array $availableIncludes = [];

    public function transform($bannerImage)
    {
        // App Module Banner
        if ($bannerImage instanceof BannerImageModel) {
            $isAppModuleBanner = (string) $bannerImage->bannertype === '1';
            $module = $bannerImage->appmodule ?? $bannerImage->appModule;

            return [
                'preview_image' => $bannerImage->previewimage_url,
                'image_url'     => $isAppModuleBanner ? null : $bannerImage->imageurl,
                'active'        => $bannerImage->active,
                'module' => $isAppModuleBanner && $module ? [
                    'name'           => $module->name,
                    'transport_type' => $module->transport_type,
                    'service_type'   => $module->service_type,
                ] : null,
            ];
        }

        // Normal Banner
        if ($bannerImage instanceof BannerImage) {
            return [
                'image'     => $bannerImage->image,
                'image_url' => $bannerImage->image_url ?? null,
                'active'    => $bannerImage->active,
            ];
        }

        return [];
    }
}