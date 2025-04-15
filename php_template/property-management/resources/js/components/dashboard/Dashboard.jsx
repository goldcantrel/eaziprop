// resources/js/components/dashboard/Dashboard.jsx
import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { supabase } from '../../services/supabase';

export default function Dashboard() {
  const [user, setUser] = useState(null);
  const [properties, setProperties] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadUserData() {
      const { data: { user } } = await supabase.auth.getUser();
      setUser(user);
      setLoading(false);
    }
    loadUserData();
  }, []);

  return (
    <div className="min-h-screen bg-gray-100">
      <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="px-4 py-6 sm:px-0">
          <h1 className="text-3xl font-bold text-gray-900">Dashboard</h1>
          <div className="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <Link to="/properties" className="block p-6 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
              <h3 className="text-lg font-medium text-gray-900">Properties</h3>
              <p className="mt-2 text-sm text-gray-500">Manage your properties</p>
            </Link>
            <Link to="/rentals" className="block p-6 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
              <h3 className="text-lg font-medium text-gray-900">Rentals</h3>
              <p className="mt-2 text-sm text-gray-500">View and manage rentals</p>
            </Link>
            <Link to="/maintenance" className="block p-6 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
              <h3 className="text-lg font-medium text-gray-900">Maintenance</h3>
              <p className="mt-2 text-sm text-gray-500">Handle maintenance requests</p>
            </Link>
            <Link to="/payments" className="block p-6 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
              <h3 className="text-lg font-medium text-gray-900">Payments</h3>
              <p className="mt-2 text-sm text-gray-500">Track payments and expenses</p>
            </Link>
            <Link to="/chat" className="block p-6 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
              <h3 className="text-lg font-medium text-gray-900">Chat</h3>
              <p className="mt-2 text-sm text-gray-500">Communicate with tenants</p>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}