# Second-Hand Inventory Management System

## Overview
This system provides comprehensive tracking and management for second-hand inventory items, including trade-ins, donations, abandoned items, and parts from dismantled machines. The system integrates with the existing RMA infrastructure while providing specialized functionality for second-hand goods.

## Features

### 1. Dual Tracking System
- **Preprinted Codes (DSH)**: For items with pre-applied DSH labels (e.g., DSH1, DSH2)
- **Generated Tracking Codes (SH)**: For items without pre-applied labels (e.g., SH1, SH2)

### 2. Item Source Tracking
- Trade-ins
- Donations
- Abandoned items
- Parts from dismantled machines
- Purchased items
- Other sources

### 3. Scottish Compliance
- Customer identification requirements
- Documentation standards
- Record keeping for legal compliance

### 4. Comprehensive Item Details
- Item name, category, brand, model
- Serial numbers
- Condition ratings (Excellent, Good, Fair, Poor)
- Detailed condition notes
- Location tracking

### 5. Photo Management
- **Item Photos**: Capture and store photos of items
- **Camera Integration**: Direct camera access for photo capture
- **File Upload**: Support for uploading existing images
- **Photo Association**: Link photos to specific items
- **Photo Display**: View photos in item details

### 6. Financial Tracking
- Purchase price (what you paid)
- Estimated value
- Estimated sale price
- Valuation reports

## Database Structure

### second_hand_items table
- `id`: Primary key
- `preprinted_code`: DSH format tracking code
- `tracking_code`: SH format tracking code
- `item_name`: Name of the item
- `condition`: Item condition (excellent, good, fair, poor)
- `item_source`: Source of the item
- `serial_number`: Item serial number
- `status`: Current status (in_stock, sold, etc.)
- `purchase_price`: Amount paid for the item
- `estimated_sale_price`: Estimated selling price
- `estimated_value`: Estimated value at acquisition
- `customer_id`, `customer_name`, `customer_contact`: Customer information
- `category`: Item category
- `detailed_condition`: Detailed condition notes
- `location`: Current location
- `acquisition_date`: Date acquired
- `warranty_info`: Warranty information
- `supplier_info`: Supplier information
- `model_number`, `brand`: Product details
- `purchase_document`: Document reference
- `status_detail`: Additional status information
- `notes`: General notes
- `trade_in_reference`: Reference to trade-in if applicable

### Enhanced trade_in_items table
- All original fields plus:
- `customer_name`, `customer_phone`, `customer_email`, `customer_address`
- `id_document_type`, `id_document_number`
- `compliance_notes`
- `collection_date`
- `compliance_status`

## API Endpoints

### PHP Directory (`/secondhand/php/`)
- `setup.php`: Database schema setup and updates
- `generate-tracking.php`: Generate SH tracking codes
- `generate-preprinted.php`: Generate DSH preprinted codes
- `save_second_hand_item.php`: Save/update second-hand items
- `get_second_hand_item.php`: Get specific item details
- `list_second_hand_items.php`: List items with filtering
- `import_trade_in.php`: Import trade-in items to second-hand inventory
- `add_item.php`: Add new items from various sources
- `scottish_compliance.php`: Verify Scottish compliance
- `reports.php`: Generate various reports
- `bulk_operations.php`: Bulk update operations
- `valuation.php`: Valuation and aging reports

### AJAX Directory (`/secondhand/ajax/`)
- All trade-in related API endpoints

## Reports Available

1. **Summary Report**: Overall inventory statistics
2. **Inventory Report**: Complete item listing
3. **Trade-in Report**: Trade-in specific items
4. **Valuation Report**: Financial valuation by condition/source
5. **Aging Report**: How long items have been in inventory
6. **Slow Moving Report**: Items in inventory over 90 days

## Trade-In Forms

- **Printable Trade-In Receipts**: Generate customer signature forms
- **Compliance Documentation**: Required for Scottish regulations
- **Customer Acknowledgment**: Legal protection for both parties

## Compliance Features

The system includes specific features to meet Scottish second-hand goods regulations:
- Customer identification requirements
- Document verification
- Record keeping standards
- Compliance status tracking

## Security

### Permissions System
- **Permissions-based access**: Similar to the RMA system with granular permissions
- **Admin override**: Admin and useradmin roles can bypass basic access checks but specific actions still require permissions
- **Available permissions**:
  - `SecondHand-View`: Basic view access
  - `SecondHand-View All Locations`: Access to view items at all locations
  - `SecondHand-Manage`: Ability to add/edit items
  - `SecondHand-View Financial`: Access to financial information (prices, costs)
  - `SecondHand-View Customer Data`: Access to customer information
  - `SecondHand-View Documents`: Access to documents and images
  - `SecondHand-Import Trade Ins`: Permission to import trade-in items
  - `SecondHand-Manage Compliance`: Permission to manage compliance aspects

### Location-Based Access
- Users can be restricted to viewing items only at their assigned location
- Admins and users with "View All Locations" permission can see items across all locations
- Location restrictions apply to viewing, editing, and creating items

### Audit Logging
- All changes to items are logged with:
  - User who made the change
  - Timestamp
  - Type of action (create, update, delete, import)
  - Before and after values for updates

### Data Privacy
- Customer information access is controlled by permissions
- Document access is restricted by permissions
- Financial data is protected by separate permissions

## Setup

1. Run the setup script: `/secondhand/php/setup.php`
2. This will create/upgrade the necessary database tables
3. Access the system through `/secondhand/secondhand.php`
4. Grant permissions to users through the Control Panel (new "Second-Hand Inventory" section added)

## Trade-in Integration

The system seamlessly integrates with the existing trade-in workflow:
- Complete customer information capture
- Document verification
- Scottish compliance checking
- Automatic import to second-hand inventory