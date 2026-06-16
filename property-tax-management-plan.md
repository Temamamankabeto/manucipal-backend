# Property and Tax Management Integration

Core modules:
- Property Management
- Property Tax Management
- Valuation Management
- Billing and Payment
- Tax Clearance
- GIS Integration

Database tables:
- property_categories
- properties
- property_owners
- property_documents
- property_valuations
- property_transfers
- property_addresses
- citizen_properties
- tax_types
- property_taxes
- tax_assessments
- tax_payments
- tax_penalties
- tax_clearances

Roles:
- Property Officer
- Property Registrar
- Valuation Officer
- Tax Assessor
- Revenue Collector
- Finance Officer
- Tax Auditor
- GIS Officer

Permissions:
- properties.read
- properties.create
- properties.update
- properties.delete
- taxes.read
- taxes.assess
- taxes.collect
- taxes.clearance

Frontend pages:
- /dashboard/properties
- /dashboard/property-categories
- /dashboard/valuations
- /dashboard/tax-assessments
- /dashboard/tax-payments
- /dashboard/tax-clearances

Public pages:
- /public/property-verification
- /public/tax-check
- /public/tax-payment
