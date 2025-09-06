Installation Guide
Overview
This system integrates Gemini AI into your Productivity Hub to analyze diary entries for depression and suicidal tendency indicators. It provides real-time mental health insights, crisis alerts, and comprehensive reporting.
Prerequisites

PHP 7.4 or higher with PDO MySQL extension
MySQL 5.7 or higher
Web server (Apache/Nginx)
Gemini AI API Key from Google AI Studio
HTTPS enabled (recommended for security)

Installation Steps
Step 1: Database Setup

Run the existing database setup:

sql-- Execute your existing db.txt first
SOURCE db.txt;

Add mental health tables:

sql-- Execute mental_health_tables.sql
SOURCE mental_health_tables.sql;
Step 2: Get Gemini AI API Key

Visit Google AI Studio
Create a new API key
Copy the API key for configuration

Step 3: File Configuration

Update gemini_config.php:

php<?php
// Replace with your actual Gemini API key
define('GEMINI_API_KEY', 'YOUR_ACTUAL_API_KEY_HERE');

// Update crisis hotline for your region
define('CRISIS_HOTLINE', '988'); // US: 988, UK: 116 123, etc.
?>

Upload all files to your web directory:

your-website/
├── config.php (existing)
├── db_connect.php (existing)
├── index.php (replace with updated version)
├── gemini_config.php (new)
├── mental_health_analyzer.php (new)
├── mental_health_dashboard.php (new)
├── mental_health_report.php (new)
├── mental_health_tables.sql (new)
├── script.js (existing)
└── styles.css (existing)
Step 4: File Permissions
Set appropriate permissions:
bashchmod 644 *.php
chmod 600 gemini_config.php  # More restrictive for API key
Step 5: Test Installation

Test database connection:

Visit your website
Try logging in with existing credentials


Test mental health analysis:

Go to Diary section
Write a test entry
Click "Analyze Mental Health" button


Check mental health dashboard:

Visit /mental_health_dashboard.php
Verify all sections load correctly



Configuration Options
Mental Health Settings
Users can configure:

Enable/Disable Analysis: Turn mental health analysis on/off
Analysis Frequency: Every entry, daily, or weekly
Notifications: Enable crisis notifications
Privacy Level: Private or anonymous research participation
Crisis Contacts: Personal emergency contacts

System Configuration
Crisis Thresholds (in gemini_config.php):
phpdefine('DEPRESSION_HIGH_THRESHOLD', 7);     // 0-10 scale
define('DEPRESSION_MODERATE_THRESHOLD', 4); // 0-10 scale  
define('SUICIDE_RISK_THRESHOLD', 5);        // 0-10 scale
Regional Resources:
Update the mental_health_resources table with local resources:
sqlINSERT INTO mental_health_resources (title, description, phone, category) VALUES
('Your Local Crisis Line', 'Local 24/7 support', 'YOUR-LOCAL-NUMBER', 'crisis'),
('Local Mental Health Center', 'Professional support', 'CENTER-NUMBER', 'therapy');
Security Considerations
API Key Security

Store API key in environment variables (recommended):

phpdefine('GEMINI_API_KEY', $_ENV['GEMINI_API_KEY']);
Data Privacy

All mental health data is stored locally
No data is sent to third parties except Gemini AI for analysis
Users can opt out of analysis
Implement GDPR compliance if needed

HTTPS

Required for production to protect sensitive data
Use SSL certificate from Let's Encrypt or commercial provider

Features
1. Real-time Analysis

Analyzes diary entries using Gemini AI
Provides depression and suicide risk scores (0-10 scale)
Identifies urgency levels: low, moderate, high, critical

2. Crisis Detection

Automatic alerts for high-risk situations
Notifications to users with resources
Crisis contact integration

3. Mental Health Dashboard

Overview of mental health trends
30-day analysis summary
Crisis alert management
Resource directory

4. Comprehensive Reporting

Detailed mental health reports
Trend analysis over time
Professional recommendations
Exportable in multiple formats

5. Privacy Controls

User-controlled analysis settings
Data retention policies
Anonymous participation options

Troubleshooting
Common Issues
1. API Key Errors
Error: Failed to call Gemini API
Solution: Verify API key is correct and has proper permissions
2. Database Connection Issues
Error: Connection failed
Solution: Check database credentials in config.php
3. Analysis Not Working
Error: Mental health analysis failed
Solutions:

Check API key validity
Verify internet connection
Check error logs for detailed messages

4. Missing Tables
Error: Table 'mental_health_records' doesn't exist
Solution: Run mental_health_tables.sql
Debug Mode
Enable PHP error reporting for debugging:
phpini_set('display_errors', 1);
error_reporting(E_ALL);
Monitoring and Maintenance
Regular Tasks

Monitor API usage to avoid quota limits
Review crisis alerts for follow-up actions
Update mental health resources regularly
Backup mental health data securely

Log Monitoring
Monitor these logs:

PHP error logs for system issues
Crisis alerts for high-risk users
API usage for quota management

Legal and Ethical Considerations
Important Disclaimers

This system is not a replacement for professional mental health care
AI analysis may have false positives/negatives
Users in crisis should be directed to professional help immediately

Compliance

HIPAA compliance may be required for healthcare settings
Data protection laws (GDPR, CCPA) may apply
Liability considerations for crisis detection systems

Professional Integration
Consider integrating with:

Licensed mental health professionals
Crisis intervention teams
Healthcare systems
Employee assistance programs

Support and Resources
Crisis Hotlines (Update for your region)

US: 988 (Suicide & Crisis Lifeline)
UK: 116 123 (Samaritans)
Canada: 1-833-456-4566 (Talk Suicide Canada)
Australia: 13 11 14 (Lifeline)

Professional Resources

National Alliance on Mental Illness (NAMI)
American Psychological Association (APA)
Local mental health organizations

Version History

v1.0: Initial release with basic analysis
v1.1: Added crisis detection and alerts
v1.2: Enhanced dashboard and reporting
v1.3: Added privacy controls and settings

Contributing
To contribute to this system:

Follow mental health best practices
Ensure privacy and security compliance
Test thoroughly with various scenarios
Document all changes

License
This system is provided for educational and therapeutic purposes. Please ensure compliance with local healthcare regulations and obtain proper professional oversight before production use.
