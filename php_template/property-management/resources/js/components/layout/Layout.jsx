// resources/js/components/layout/Layout.jsx
import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import Login from '../auth/Login';
import Register from '../auth/Register';
import PropertyList from '../property/PropertyList';
import PropertyForm from '../property/PropertyForm';
import RentalList from '../rental/RentalList';
import PaymentList from '../payment/PaymentList';
import MaintenanceList from '../maintenance/MaintenanceList';
import DocumentList from '../document/DocumentList';
import ChatWindow from '../chat/ChatWindow';
import { useSupabase } from '../../services/supabase';

const Layout = () => {
  const { user } = useSupabase();

  return (
    <div className="min-h-screen bg-gray-100">
      <nav className="bg-white shadow-lg">
        <div className="max-w-7xl mx-auto px-4">
          <div className="flex justify-between h-16">
            <div className="flex">
              <div className="flex-shrink-0 flex items-center">
                <h1 className="text-xl font-bold">Property Management</h1>
              </div>
            </div>
          </div>
        </div>
      </nav>

      <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <Routes>
          <Route path="/login" element={!user ? <Login /> : <Navigate to="/" />} />
          <Route path="/register" element={!user ? <Register /> : <Navigate to="/" />} />
          <Route path="/properties" element={user ? <PropertyList /> : <Navigate to="/login" />} />
          <Route path="/properties/new" element={user ? <PropertyForm /> : <Navigate to="/login" />} />
          <Route path="/rentals" element={user ? <RentalList /> : <Navigate to="/login" />} />
          <Route path="/payments" element={user ? <PaymentList /> : <Navigate to="/login" />} />
          <Route path="/maintenance" element={user ? <MaintenanceList /> : <Navigate to="/login" />} />
          <Route path="/documents" element={user ? <DocumentList /> : <Navigate to="/login" />} />
          <Route path="/chat" element={user ? <ChatWindow /> : <Navigate to="/login" />} />
          <Route path="/" element={<Navigate to="/properties" />} />
        </Routes>
      </main>
    </div>
  );
};

export default Layout;