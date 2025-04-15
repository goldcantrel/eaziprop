// resources/js/components/maintenance/Maintenance.jsx
import React, { useState, useEffect } from 'react';
import { supabase } from '../../services/supabase';

export default function Maintenance() {
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadMaintenanceRequests();
  }, []);

  async function loadMaintenanceRequests() {
    const { data: { user } } = await supabase.auth.getUser();
    const { data, error } = await supabase
      .from('properties_593nwd_maintenance_requests')
      .select('*')
      .eq('user_email', user.email);

    if (error) {
      console.error('Error loading maintenance requests:', error);
    } else {
      setRequests(data || []);
    }
    setLoading(false);
  }

  return (
    <div className="min-h-screen bg-gray-100 p-6">
      <div className="max-w-7xl mx-auto">
        <h1 className="text-3xl font-bold text-gray-900 mb-6">Maintenance Requests</h1>
        {loading ? (
          <div>Loading maintenance requests...</div>
        ) : (
          <div className="grid grid-cols-1 gap-6">
            {requests.map((request) => (
              <div key={request.id} className="bg-white shadow rounded-lg p-6">
                <div className="flex justify-between items-start">
                  <h3 className="text-lg font-medium text-gray-900">{request.title}</h3>
                  <span className={`px-2 py-1 rounded text-sm ${
                    request.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                    request.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                    'bg-green-100 text-green-800'
                  }`}>
                    {request.status}
                  </span>
                </div>
                <p className="mt-2 text-gray-600">{request.description}</p>
                <div className="mt-4 text-sm text-gray-500">
                  <p>Property: {request.property_name}</p>
                  <p>Reported: {new Date(request.created_at).toLocaleDateString()}</p>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}