// resources/js/services/supabase.js
import { createClient } from '@supabase/supabase-js';

const supabaseUrl = 'https://bwnpeubmjamwjaptsspo.supabase.co';
const supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ3bnBldWJtamFtd2phcHRzc3BvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDQ3NTQ5MDUsImV4cCI6MjA2MDMzMDkwNX0.xyDrDaqG2vsuI7jsxjJUIFnwUt3c97dVDJuCR8ATxfc';

export const supabase = createClient(supabaseUrl, supabaseAnonKey);