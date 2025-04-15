// resources/js/app.jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { supabase } from './services/supabase';

// Lazy load components for better performance
const Login = React.lazy(() => import('./components/auth/Login'));
const Register = React.lazy(() => import('./components/auth/Register'));
const Dashboard = React.lazy(() => import('./components/dashboard/Dashboard'));
const Properties = React.lazy(() => import('./components/property/Properties'));
const Rentals = React.lazy(() => import('./components/rental/Rentals'));
const Maintenance = React.lazy(() => import('./components/maintenance/Maintenance'));
const Payments = React.lazy(() => import('./components/payment/Payments'));
const Chat = React.lazy(() => import('./components/chat/Chat'));

function App() {
    const [session, setSession] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        // Get initial session
        supabase.auth.getSession().then(({ data: { session } }) => {
            setSession(session);
            setLoading(false);
        });

        // Listen for auth changes
        const {
            data: { subscription },
        } = supabase.auth.onAuthStateChange((_event, session) => {
            setSession(session);
        });

        return () => subscription.unsubscribe();
    }, []);

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <div className="w-16 h-16 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
            </div>
        );
    }

    return (
        <Router>
            <React.Suspense fallback={
                <div className="flex items-center justify-center min-h-screen">
                    <div className="w-16 h-16 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                </div>
            }>
                <Routes>
                    <Route path="/login" element={!session ? <Login /> : <Navigate to="/" />} />
                    <Route path="/register" element={!session ? <Register /> : <Navigate to="/" />} />
                    <Route path="/" element={session ? <Dashboard /> : <Navigate to="/login" />} />
                    <Route path="/properties" element={session ? <Properties /> : <Navigate to="/login" />} />
                    <Route path="/rentals" element={session ? <Rentals /> : <Navigate to="/login" />} />
                    <Route path="/maintenance" element={session ? <Maintenance /> : <Navigate to="/login" />} />
                    <Route path="/payments" element={session ? <Payments /> : <Navigate to="/login" />} />
                    <Route path="/chat" element={session ? <Chat /> : <Navigate to="/login" />} />
                </Routes>
            </React.Suspense>
        </Router>
    );
}

const container = document.getElementById('app');
const root = createRoot(container);
root.render(<App />);