<?php

// Kullanılan kurallar için Türkçe doğrulama mesajları. Yeni bir kural kullanıldığında buraya eklenmeli.

return [

    'accepted' => ':attribute onaylanmalıdır.',
    'after' => ':attribute, :date tarihinden sonra olmalıdır.',
    'after_or_equal' => ':attribute, :date tarihi ya da sonrası olmalıdır.',
    'array' => ':attribute bir liste olmalıdır.',
    'before' => ':attribute, :date tarihinden önce olmalıdır.',
    'before_or_equal' => ':attribute, :date tarihi ya da öncesi olmalıdır.',
    'between' => [
        'array' => ':attribute :min ile :max öğe arasında olmalıdır.',
        'numeric' => ':attribute :min ile :max arasında olmalıdır.',
        'string' => ':attribute :min ile :max karakter arasında olmalıdır.',
    ],
    'boolean' => ':attribute doğru ya da yanlış olmalıdır.',
    'date' => ':attribute geçerli bir tarih olmalıdır.',
    'date_format' => ':attribute :format biçiminde olmalıdır.',
    'digits' => ':attribute :digits haneli olmalıdır.',
    'digits_between' => ':attribute :min ile :max hane arasında olmalıdır.',
    'distinct' => ':attribute alanında tekrar eden değer var.',
    'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'enum' => 'Seçilen :attribute geçersiz.',
    'exists' => 'Seçilen :attribute geçersiz.',
    'in' => 'Seçilen :attribute geçersiz.',
    'integer' => ':attribute tam sayı olmalıdır.',
    'max' => [
        'array' => ':attribute en fazla :max öğe içerebilir.',
        'numeric' => ':attribute en fazla :max olabilir.',
        'string' => ':attribute en fazla :max karakter olabilir.',
    ],
    'min' => [
        'array' => ':attribute en az :min öğe içermelidir.',
        'numeric' => ':attribute en az :min olmalıdır.',
        'string' => ':attribute en az :min karakter olmalıdır.',
    ],
    'numeric' => ':attribute sayı olmalıdır.',
    'present' => ':attribute alanı gönderilmelidir.',
    'prohibited' => ':attribute alanı gönderilemez.',
    'regex' => ':attribute biçimi geçersiz.',
    'required' => ':attribute alanı zorunludur.',
    'required_if' => ':attribute alanı zorunludur.',
    'required_with' => ':attribute alanı zorunludur.',
    'size' => [
        'array' => ':attribute :size öğe içermelidir.',
        'numeric' => ':attribute :size olmalıdır.',
        'string' => ':attribute :size karakter olmalıdır.',
    ],
    'string' => ':attribute metin olmalıdır.',
    'unique' => 'Bu :attribute zaten kullanılıyor.',
    'url' => ':attribute geçerli bir bağlantı olmalıdır.',

    'custom' => [],

    'attributes' => [
        'phone' => 'telefon numarası',
        'code' => 'kod',
        'device_name' => 'cihaz adı',
        'name' => 'ad',
        'platform' => 'platform',
        'fcm_token' => 'bildirim anahtarı',
        'email' => 'e-posta',
        'password' => 'şifre',
    ],

];
