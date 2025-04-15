<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supabase Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure your Supabase settings. The URL and anon key
    | can be found in your Supabase project settings.
    |
    */

    'url' => env('SUPABASE_URL'),
    'key' => env('SUPABASE_KEY'),
    'webhook_secret' => env('SUPABASE_WEBHOOK_SECRET'),
    
    /*
    |--------------------------------------------------------------------------
    | Database Schema
    |--------------------------------------------------------------------------
    |
    | The schema to use for the Supabase database.
    |
    */
    'schema' => env('SUPABASE_SCHEMA', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | List of tables in the database with their respective configurations.
    |
    */
    'tables' => [
        'users' => [
            'id' => 'uuid',
            'name' => 'text',
            'email' => 'text',
            'role' => 'text',
            'phone' => 'text',
            'address' => 'text',
            'created_at' => 'timestamp with time zone',
            'updated_at' => 'timestamp with time zone'
        ],
        'properties' => [
            'id' => 'uuid',
            'owner_id' => 'uuid references users(id)',
            'title' => 'text',
            'description' => 'text',
            'address' => 'text',
            'type' => 'text',
            'price' => 'numeric',
            'bedrooms' => 'integer',
            'bathrooms' => 'numeric',
            'square_feet' => 'numeric',
            'available_from' => 'date',
            'status' => 'text',
            'amenities' => 'jsonb',
            'created_at' => 'timestamp with time zone',
            'updated_at' => 'timestamp with time zone'
        ],
        'rentals' => [
            'id' => 'uuid',
            'property_id' => 'uuid references properties(id)',
            'tenant_id' => 'uuid references users(id)',
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'numeric',
            'deposit_amount' => 'numeric',
            'payment_day' => 'integer',
            'payment_frequency' => 'text',
            'status' => 'text',
            'created_at' => 'timestamp with time zone',
            'updated_at' => 'timestamp with time zone'
        ],
        'payments' => [
            'id' => 'uuid',
            'rental_id' => 'uuid references rentals(id)',
            'amount' => 'numeric',
            'payment_method' => 'text',
            'payment_date' => 'date',
            'due_date' => 'date',
            'status' => 'text',
            'transaction_id' => 'text',
            'notes' => 'text',
            'created_at' => 'timestamp with time zone',
            'updated_at' => 'timestamp with time zone'
        ],
        'maintenance_requests' => [
            'id' => 'uuid',
            'property_id' => 'uuid references properties(id)',
            'tenant_id' => 'uuid references users(id)',
            'assigned_to' => 'uuid references users(id)',
            'title' => 'text',
            'description' => 'text',
            'priority' => 'text',
            'status' => 'text',
            'photos' => 'jsonb',
            'estimated_cost' => 'numeric',
            'actual_cost' => 'numeric',
            'completed_at' => 'timestamp with time zone',
            'created_at' => 'timestamp with time zone',
            'updated_at' => 'timestamp with time zone'
        ],
        'documents' => [
            'id' => 'uuid',
            'property_id' => 'uuid references properties(id)',
            'uploaded_by' => 'uuid references users(id)',
            'title' => 'text',
            'type' => 'text',
            'file_path' => 'text',
            'file_name' => 'text',
            'file_type' => 'text',
            'file_size' => 'bigint',
            'description' => 'text',
            'expiry_date' => 'date',
            'created_at' => 'timestamp with time zone',
            'updated_at' => 'timestamp with time zone'
        ],
        'chat_messages' => [
            'id' => 'uuid',
            'sender_id' => 'uuid references users(id)',
            'receiver_id' => 'uuid references users(id)',
            'property_id' => 'uuid references properties(id)',
            'maintenance_request_id' => 'uuid references maintenance_requests(id)',
            'message' => 'text',
            'message_type' => 'text',
            'file_path' => 'text',
            'is_read' => 'boolean',
            'created_at' => 'timestamp with time zone',
            'updated_at' => 'timestamp with time zone'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | RLS (Row Level Security) Policies
    |--------------------------------------------------------------------------
    |
    | Define RLS policies for each table to control access at the row level.
    |
    */
    'policies' => [
        'users' => [
            'select' => 'true', // All authenticated users can select users
            'insert' => 'auth.uid() = id', // Users can only insert their own records
            'update' => 'auth.uid() = id', // Users can only update their own records
            'delete' => 'false' // No direct deletion allowed
        ],
        'properties' => [
            'select' => 'true', // All authenticated users can view properties
            'insert' => "auth.user()->role IN ('landlord', 'admin')",
            'update' => "auth.user()->role IN ('landlord', 'admin') AND owner_id = auth.uid()",
            'delete' => "auth.user()->role IN ('landlord', 'admin') AND owner_id = auth.uid()"
        ],
        'rentals' => [
            'select' => 'auth.uid() = tenant_id OR EXISTS (SELECT 1 FROM properties WHERE id = property_id AND owner_id = auth.uid())',
            'insert' => "auth.user()->role IN ('landlord', 'admin')",
            'update' => "auth.user()->role IN ('landlord', 'admin') AND EXISTS (SELECT 1 FROM properties WHERE id = property_id AND owner_id = auth.uid())",
            'delete' => "auth.user()->role = 'admin'"
        ]
    ]
];
