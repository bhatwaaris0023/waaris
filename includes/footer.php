<?php
/**
 * Include Files - Footer
 */
?>
    </main>
    
    <footer class="footer">
        <div class="footer-gradient"></div>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4 class="footer-heading">About Ahanger MotoCorp</h4>
                    <p class="footer-text">Premium motorcycles and accessories for enthusiasts who demand quality and performance.</p>
                    <div class="social-links">
                        <a href="#" class="social-link" title="Facebook">f</a>
                        <a href="#" class="social-link" title="Twitter">𝕏</a>
                        <a href="#" class="social-link" title="Instagram">📷</a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4 class="footer-heading">Quick Navigation</h4>
                    <ul class="footer-links">
                        <li><a href="/waaris/">Home</a></li>
                        <li><a href="/waaris/products.php">Products</a></li>
                        <li><a href="/waaris/cart.php">Shopping Cart</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4 class="footer-heading">Get In Touch</h4>
                    <div class="contact-info">
                        <p><strong>Email:</strong> <a href="mailto:info@ahangermotocorp.com">info@ahangermotocorp.com</a></p>
                        <p><strong>Phone:</strong> <a href="tel:+18005686267">+1 (800) MOTO-CORP</a></p>
                        <p><strong>Hours:</strong> Mon-Sat 9AM-6PM EST</p>
                    </div>
                </div>
            </div>
            
            <div class="footer-divider"></div>
            
            <div class="footer-bottom">
                <div class="footer-copyright">
                    <p>&copy; 2026 <strong>Ahanger MotoCorp</strong>. All rights reserved.</p>
                </div>
                <div class="footer-legal">
                    <a href="#privacy">Privacy Policy</a>
                    <span class="divider">•</span>
                    <a href="#terms">Terms of Service</a>
                    <span class="divider">•</span>
                    <a href="#cookies">Cookie Settings</a>
                </div>
            </div>
        </div>
    </footer>
    
    <style>
        footer.footer {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #ecf0f1;
            position: relative;
            margin-top: 60px;
        }
        
        .footer-gradient {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            padding: 50px 0 30px 0;
        }
        
        .footer-section h4.footer-heading {
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .footer-text {
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            opacity: 0.85;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #bdc3c7;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .footer-links a:hover {
            color: #667eea;
            padding-left: 5px;
        }
        
        .contact-info {
            font-size: 14px;
            line-height: 1.8;
        }
        
        .contact-info p {
            margin: 8px 0;
        }
        
        .contact-info a {
            color: #667eea;
            text-decoration: none;
        }
        
        .contact-info a:hover {
            text-decoration: underline;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 50%;
            color: #667eea;
            text-decoration: none;
            font-size: 18px;
            font-weight: bold;
            transition: all 0.3s ease;
            border: 1px solid rgba(102, 126, 234, 0.3);
        }
        
        .social-link:hover {
            background: #667eea;
            color: #fff;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .footer-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            margin: 30px 0;
        }
        
        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            padding: 20px 0;
        }
        
        .footer-copyright p {
            margin: 0;
            font-size: 14px;
            opacity: 0.8;
        }
        
        .footer-legal {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .footer-legal a {
            color: #bdc3c7;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
        }
        
        .footer-legal a:hover {
            color: #667eea;
        }
        
        .divider {
            color: rgba(255, 255, 255, 0.3);
        }
        
        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }
            
            .footer-legal {
                justify-content: center;
                flex-wrap: wrap;
            }
        }
    </style>
    
    <script src="/waaris/assets/js/main.js"></script>
    <?php if (isset($additional_scripts)): ?>
        <?php foreach ($additional_scripts as $script): ?>
            <script src="<?php echo htmlspecialchars($script); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
