This project appears to be a simple e-commerce web application called **"La Nueva Venta"**. It allows users to register, log in, upload products, view products, add them to a cart, and make purchases. Here's how the different components work together:

---

### **1. User Registration (`registro.html`)**
- **Form**: Users can register by filling out their name, email, and password.
- **Validation**: 
  - Passwords must match.
  - Duplicate emails are not allowed.
- **Storage**: 
  - User data is stored in 

localStorage

 under the key 

usuarios

.
  - The logged-in user is stored in 

localStorage

 as `usuarioLogueado`.
- **Redirection**: After successful registration, the user is redirected to 

index.html

.

---

### **2. User Login (`login.html`)**
- **Form**: Users log in using their email and password.
- **Validation**: 
  - The email and password are checked against the 

usuarios

 stored in 

localStorage

.
  - If valid, the user is stored as `usuarioLogueado`.
- **Error Handling**: If the credentials are invalid, an error message is displayed.
- **Redirection**: Successful login redirects the user to 

index.html

.

---

### **3. Navbar Dropdown (`script.js`)**
- **Dynamic Dropdown**: 
  - If a user is logged in, the dropdown shows options like "Cuenta", "Subir Producto", and "Cerrar Sesión".
  - If no user is logged in, it shows options to log in or register.
- **Logout**: Clicking "Cerrar Sesión" clears `usuarioLogueado` from 

localStorage

.

---

### **4. Product Upload (`subirProducto.html`)**
- **Form**: Logged-in users can upload products with details like name, price, stock, description, and an image.
- **Validation**: 
  - Stock must be greater than 0.
  - An image must be selected.
- **Storage**: 
  - Products are stored in 

localStorage

 under the key 

productos

.
  - Each product is associated with the email of the logged-in user.
- **Redirection**: After successful upload, the user is redirected to 

index.html

.

---

### **5. Product Display (`index.html` and 

producto.html

)**
- **Carousel (`index.html`)**: 
  - Products are displayed in a carousel.
  - Products are grouped into slides of 3.
- **Product Details (

producto.html

)**: 
  - Clicking on a product opens its details page.
  - The product ID is stored in 

localStorage

 as `productoSeleccionado`.
  - The product's name, price, description, and images are displayed.

---

### **6. Shopping Cart (

carrito.html

)**
- **Add to Cart**: 
  - Users can add products to their cart from the product details page.
  - The cart is stored in 

localStorage

 under the key 

carritos

, grouped by user email.
- **Cart Display**: 
  - The cart page shows all products in the user's cart.
  - The total price is calculated dynamically.
- **Remove from Cart**: 
  - Users can remove products from the cart.
  - The cart is updated in 

localStorage

.
- **Purchase**: 
  - Clicking "Realizar compra" clears the cart and displays a success message.

---

### **7. User Account (`cuenta.html`)**
- **Uploaded Products**: 
  - Displays all products uploaded by the logged-in user.
  - Products are filtered by the user's email.
- **Delete Product**: 
  - Users can delete their uploaded products.
  - The product is removed from 

localStorage

.

---

### **8. Utility Functions (`script.js`)**
- **

getCarritoUsuario()

**: Retrieves the cart for the logged-in user.
- **

setCarritoUsuario()

**: Updates the cart for the logged-in user.
- **

renderCarrito()

**: Dynamically updates the cart display.
- **

renderCarrusel()

**: Dynamically updates the product carousel.

---

### **9. LocalStorage Usage**
- **Keys**:
  - 

usuarios

: Stores all registered users.
  - `usuarioLogueado`: Stores the currently logged-in user.
  - 

productos

: Stores all uploaded products.
  - 

carritos

: Stores carts for all users.
- **Persistence**: Data persists across sessions because it is stored in 

localStorage

.

---

### **10. Technologies Used**
- **HTML**: Structure of the pages.
- **CSS**: Styling (via `styles.css` and Bootstrap).
- **JavaScript**: 
  - Handles dynamic behavior (e.g., form submissions, dropdown updates, cart management).
  - Uses 

localStorage

 for data persistence.
- **Bootstrap**: Provides responsive design and pre-styled components.

---

### **Flow of the Application**
1. **Register/Login**: Users register or log in.
2. **Upload Products**: Logged-in users can upload products.
3. **Browse Products**: All users can view products on the homepage.
4. **View Product Details**: Clicking a product shows its details.
5. **Add to Cart**: Logged-in users can add products to their cart.
6. **View Cart**: Users can view and manage their cart.
7. **Purchase**: Users can complete their purchase.
8. **Manage Account**: Users can view and delete their uploaded products.

This structure ensures a seamless user experience for an e-commerce platform.