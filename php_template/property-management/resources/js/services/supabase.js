// resources/js/services/supabase.js
import { createClient } from '@supabase/supabase-js';

const supabaseUrl = 'https://linmccfhrsteazhdqkrp.supabase.co';
const supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imxpbm1jY2ZocnN0ZWF6aGRxa3JwIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDM4MjAxNzUsImV4cCI6MjA1OTM5NjE3NX0.rEzaX_9sm-eKGJguxZITe0WmtI4zo2w1-slVaU6kOiE';

export const supabase = createClient(supabaseUrl, supabaseAnonKey);