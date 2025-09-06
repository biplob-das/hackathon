<?php
// Gemini AI Configuration
define('GEMINI_API_KEY', 'AIzaSyDxWOF9rW-f8lX3U5lK-WTg9bChnsM0ig4'); // Replace with your actual API key
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent');

// Mental health analysis thresholds
define('DEPRESSION_HIGH_THRESHOLD', 7);
define('DEPRESSION_MODERATE_THRESHOLD', 4);
define('SUICIDE_RISK_THRESHOLD', 5);

// Crisis contact information
define('CRISIS_HOTLINE', '999'); // Replace with local crisis hotline
define('MENTAL_HEALTH_RESOURCES', [
    'National Suicide Prevention Lifeline' => '988',
    'Crisis Text Line' => 'Text HOME to 741741',
    'Emergency Services' => '911'
]);
?>