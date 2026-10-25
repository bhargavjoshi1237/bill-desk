<?php

// Hindi messages for the validation rules used in this app. Anything not
// listed here falls back to Laravel's English message.

return [
    'after_or_equal' => ':attribute, :date या उसके बाद की तारीख होनी चाहिए।',
    'array' => ':attribute एक सूची होनी चाहिए।',
    'before_or_equal' => ':attribute, :date या उससे पहले की तारीख होनी चाहिए।',
    'date' => ':attribute मान्य तारीख नहीं है।',
    'email' => ':attribute मान्य ईमेल पता होना चाहिए।',
    'exists' => 'चुना गया :attribute मान्य नहीं है।',
    'file' => ':attribute एक फ़ाइल होनी चाहिए।',
    'in' => 'चुना गया :attribute मान्य नहीं है।',
    'integer' => ':attribute पूर्णांक होना चाहिए।',
    'max' => [
        'array' => ':attribute में :max से अधिक आइटम नहीं हो सकते।',
        'file' => ':attribute :max किलोबाइट से बड़ी नहीं हो सकती।',
        'numeric' => ':attribute :max से अधिक नहीं हो सकता।',
        'string' => ':attribute :max अक्षरों से अधिक नहीं हो सकता।',
    ],
    'mimetypes' => ':attribute इन प्रकारों में से होनी चाहिए: :values।',
    'min' => [
        'array' => ':attribute में कम से कम :min आइटम होने चाहिए।',
        'file' => ':attribute कम से कम :min किलोबाइट की होनी चाहिए।',
        'numeric' => ':attribute कम से कम :min होना चाहिए।',
        'string' => ':attribute कम से कम :min अक्षरों का होना चाहिए।',
    ],
    'numeric' => ':attribute एक संख्या होनी चाहिए।',
    'regex' => ':attribute का प्रारूप मान्य नहीं है।',
    'required' => ':attribute आवश्यक है।',
    'size' => [
        'string' => ':attribute ठीक :size अक्षरों का होना चाहिए।',
    ],
    'unique' => 'यह :attribute पहले से उपयोग में है।',
];
