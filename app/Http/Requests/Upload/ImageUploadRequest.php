<?php

namespace App\Http\Requests\Upload;

use Illuminate\Foundation\Http\FormRequest;

class ImageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = (int) (env('CLOUDINARY_MAX_FILE_SIZE', 5242880) / 1024); // converter para KB

        return [
            'image'  => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                "max:{$maxSize}",
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            ],
            'type'   => ['required', 'string', 'in:avatar,progress_photo'],
            'angle'  => ['nullable', 'required_if:type,progress_photo', 'string', 'in:front,back,side_left,side_right'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required'      => 'A imagem é obrigatória.',
            'image.image'         => 'O arquivo deve ser uma imagem.',
            'image.mimes'         => 'Formatos aceitos: JPEG, PNG, WebP.',
            'image.max'           => 'A imagem não pode ultrapassar 5MB.',
            'image.dimensions'    => 'Dimensões inválidas. Mín: 100x100, Máx: 4000x4000 px.',
            'type.required'       => 'O tipo de imagem é obrigatório.',
            'type.in'             => 'Tipo inválido.',
            'angle.required_if'   => 'O ângulo é obrigatório para fotos de evolução.',
        ];
    }
}
