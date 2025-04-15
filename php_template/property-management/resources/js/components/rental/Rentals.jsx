// resources/js/components/rental/Rentals.jsx
import React, { useState, useEffect } from 'react';
import { supabase } from '../../services/supabase';

export default function Rentals() {
  const [rentals, setRentals] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadRentals();
  }, []);

  async function loadRentals() {
    const { data: { user } } = await supabase.auth.getUser();
    const { data, error } = await supabase
      .from('properties_593nwd_rentals')
      .select('*')
      .eq('user_email', user.email);

    if (error) {
      console.error('Error loading rentals:', error);
    } else {
      setRentals(data || []);
    }
    setLoading(false);
  }

  return (
    <div className="min-h-screen bg-gray-100 p-6">
      <div className="max-w-7xl mx-auto">
        <h1 className="text-3xl font-bold text-gray-900 mb-6">Rentals</h1>
        {loading ? (
          <div>Loading rentals...</div>
        ) : (
          <div className="grid grid-cols-1 gap-6">
            {rentals.map((rental) => (
              <div key={rental.id} className="bg-white shadow rounded-lg p-6">
                <h3 className="text-lg font-medium text-gray-900">Unit {rental.unit_number}</h3>
                <div className="mt-4 grid grid-cols-2 gap-4 text-sm">
                  <p>Tenant: {rental.tenant_name}</p>
                  <p>Rent: ${rental.rent_amount}</p>
                  <p>Start Date: {new Date(rental.start_date).toLocaleDateString()}</p>
                  <p>End Date: {new Date(rental.end_date).toLocaleDateString()}</p>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}