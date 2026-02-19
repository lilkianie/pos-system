# Product Image Upload Feature

## Overview
Product image upload functionality has been added to the POS system. Products can now have images that are displayed in both the admin panel and POS cashiering interface.

## Features

### Image Upload
- Upload product images when adding/editing products
- Supported formats: JPEG, PNG, GIF, WebP
- Maximum file size: 5MB
- Automatic image preview before upload
- Remove image functionality

### Image Display
- **Admin Panel**: Product images shown in product list table
- **POS Interface**: Product images displayed in product grid cards
- **Search Results**: Product images shown in search results
- Fallback placeholder icon when no image is available

## Setup

### 1. Create Upload Directory
Create the upload directory if it doesn't exist:
```bash
mkdir -p assets/uploads/products
```

Or manually create:
- `assets/uploads/products/` directory

### 2. Set Permissions
Ensure the directory is writable:
```bash
chmod 755 assets/uploads/products
```

On Windows, ensure the directory has write permissions.

### 3. Access
- Go to: **Admin → Products**
- Click "Add Product" or edit an existing product
- Use the "Upload Image" button to upload product images

## Usage

### Adding Product Image
1. Go to Admin → Products
2. Click "Add Product" or edit existing product
3. In the product form, find "Product Image" section
4. Click "Choose File" and select an image
5. Click "Upload Image" button
6. Image will be uploaded and preview shown
7. Save the product

### Removing Product Image
1. When editing a product with an image
2. Click "Remove Image" button
3. Image will be removed from preview
4. Save the product

### Viewing Images
- **Admin Panel**: Images shown as thumbnails in product list
- **POS Interface**: Images displayed in product cards
- **Search**: Images shown in search results

## File Storage

- **Location**: `assets/uploads/products/`
- **Naming**: `product_{timestamp}_{uniqueid}.{extension}`
- **URL Format**: `http://possystem.com/assets/uploads/products/{filename}`

## API Endpoint

### Upload Image
```
POST /api/upload-image.php
Content-Type: multipart/form-data

Parameters:
- image: File (required)

Response:
{
    "success": true,
    "image_url": "http://possystem.com/assets/uploads/products/product_1234567890_abc123.jpg",
    "filename": "product_1234567890_abc123.jpg"
}
```

## Security

- Only users with `manage_products` permission can upload images
- File type validation (JPEG, PNG, GIF, WebP only)
- File size limit (5MB)
- Unique filenames prevent overwrites
- MIME type validation

## Image Display

### POS Product Grid
- Images displayed at top of product cards
- 180px height container
- Object-fit: cover for proper scaling
- Placeholder icon when no image

### Admin Product List
- 50x50px thumbnails in table
- Placeholder icon when no image

### Search Results
- 50x50px thumbnails in search results
- Aligned with product information

## Troubleshooting

### Image Not Uploading
1. Check directory exists: `assets/uploads/products/`
2. Check directory permissions (must be writable)
3. Check file size (max 5MB)
4. Check file format (JPEG, PNG, GIF, WebP only)

### Image Not Displaying
1. Check image URL in database
2. Verify file exists in uploads directory
3. Check file permissions
4. Check browser console for errors

### Permission Errors
- Ensure `assets/uploads/products/` directory is writable
- On Linux: `chmod 755 assets/uploads/products`
- On Windows: Check folder properties → Security → Permissions

## Notes

- Images are stored locally in the uploads directory
- Consider implementing image optimization/resizing for better performance
- For production, consider using cloud storage (AWS S3, etc.)
- Old images are not automatically deleted when product is deleted (manual cleanup needed)
