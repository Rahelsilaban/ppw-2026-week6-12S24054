<?php

return [
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'unique' => ':attribute sudah digunakan.',
    'exists' => ':attribute yang dipilih tidak tersedia.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max digit.',
    'between' => ['numeric' => ':attribute harus antara :min dan :max.'],
    'min' => ['numeric' => ':attribute minimal :min.', 'string' => ':attribute minimal :min karakter.'],
    'max' => ['numeric' => ':attribute maksimal :max.', 'string' => ':attribute maksimal :max karakter.'],
    'attributes' => ['password' => 'Kata sandi', 'kategori_id' => 'Kategori', 'nim_peminjam' => 'NIM peminjam', 'nama_peminjam' => 'Nama peminjam'],
];
