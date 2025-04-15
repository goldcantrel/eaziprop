// resources/js/components/property/Properties.jsx
import React, { useState, useEffect } from 'react';
import { supabase } from '../../services/supabase';

export default function Properties() {
  const [properties, setProperties] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadProperties();
  }, []);

  async function loadProperties() {
    const { data: { user } } = await supabase.auth.getUser();
    const { data, error } = await supabase
      .from('properties_593nwd_properties')
      .select('*')
      .eq('user_email', user.email);

    if (error) {
      console.error('Error loading properties:', error);
    } else {
      setProperties(data || []);
    }
    setLoading(false);
  }

  return (
    <div className="min-h-screen bg-gray-100 p-6">
      <div className="max-w-7xl mx-auto">
        <h1 className="text-3xl font-bold text-gray-900 mb-6">Properties</h1>
        {loading ? (
          <div>Loading properties...</div>
        ) : (
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {properties.map((property) => (
              <div key={property.id} className="bg-white shadow rounded-lg p-6">
                <h3 className="text-lg font-medium text-gray-900">{property.name}</h3>
                <p className="mt-2 text-gray-500">{property.address}</p>
                <div className="mt-4 text-sm">
                  <p>Units: {property.units}</p>
                  <p>Status: {property.status}</p>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}