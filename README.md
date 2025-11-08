# definitelynot.ai

> A Unicode-security-aware text sanitization platform defending against invisible character attacks, homoglyph spoofing, and bidirectional text exploits.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)

## 🌌 Overview

**definitelynot.ai** is home to the **Cosmic Text Linter** - a production-ready web application that sanitizes text against Unicode-based security vulnerabilities while preserving legitimate multilingual content. It features a retro 90s space-themed interface and a powerful 16-step sanitization pipeline.

### Key Features

- **🛡️ Unicode Security Defense**
  - Trojan Source (CVE-2021-42574) protection
  - Homoglyph and confusable detection
  - Zero-width steganography removal
  - Bidirectional text attack mitigation

- **🎨 Retro Cosmic Interface**
  - Animated starfield background
  - Customizable neon color themes
  - Accessible keyboard controls
  - Responsive grid layout

- **🔧 Three Operation Modes**
  - **Safe**: Preserves emoji and multilingual text
  - **Aggressive**: Latin-only, strips format characters
  - **Strict**: Maximum security with NFKC normalization

- **📊 Comprehensive Analytics**
  - Real-time character counting
  - Security advisory reports
  - Detailed sanitization statistics

## 🚀 Quick Start

### Prerequisites

- PHP 7.4+ with `mbstring` and `intl` extensions
- Apache with `mod_rewrite` (or Nginx equivalent)
- Modern browser (Chrome 90+, Firefox 88+, Safari 14+)

### Installation

```bash
# Clone the repository
git clone https://github.com/johnzfitch/definitelynot.ai.git
cd definitelynot.ai/cosmic-text-linter

# Verify PHP extensions
php -m | grep -E 'mbstring|intl'

# Deploy to web server
# For cPanel: Upload to public_html/cosmic-text-linter/
# For local dev: Use PHP built-in server
php -S localhost:8000
```

Visit `http://localhost:8000` (or your deployed URL) to access the web interface.

### API Usage

```bash
curl -X POST http://localhost:8000/api/clean.php \
  -H 'Content-Type: application/json' \
  -d '{
    "text": "Hello\u200Bworld",
    "mode": "safe"
  }'
```

## 📚 Documentation

Comprehensive documentation is organized into specialized guides:

- **[Cosmic Text Linter README](cosmic-text-linter/README.md)** - Installation, configuration, and user guide
- **[Architecture Guide](ARCHITECTURE.md)** - System design, data flow, and component diagrams
- **[API Reference](API_REFERENCE.md)** - Complete API documentation with examples
- **[Developer Guide](DEVELOPER_GUIDE.md)** - Code structure, development workflow, and extension points
- **[Security Documentation](SECURITY.md)** - Security model, threat mitigation, and best practices
- **[Contributing Guide](CONTRIBUTING.md)** - How to contribute to the project

## 🏗️ Project Structure

```
definitelynot.ai/
├── README.md                          # This file
├── ARCHITECTURE.md                    # System architecture documentation
├── API_REFERENCE.md                   # API documentation
├── DEVELOPER_GUIDE.md                 # Developer documentation
├── SECURITY.md                        # Security documentation
├── CONTRIBUTING.md                    # Contribution guidelines
└── cosmic-text-linter/                # Main application
    ├── index.html                     # Web UI entry point
    ├── README.md                      # Detailed user guide
    ├── api/
    │   ├── TextLinter.php            # Core sanitization engine
    │   └── clean.php                  # RESTful API endpoint
    ├── assets/
    │   ├── css/styles.css            # Retro cosmic theme
    │   └── js/script.js              # Frontend controller
    └── tests/
        ├── test-samples.txt           # 20+ test cases
        └── smoke-test.sh              # Automated API tests
```

## 🎯 Use Cases

- **Security Teams**: Sanitize user-generated content before database storage
- **Content Platforms**: Defend against Unicode-based attacks in comments/posts
- **Code Review Tools**: Detect Trojan Source vulnerabilities in source code
- **Email Systems**: Clean email content from invisible tracking characters
- **Data Migration**: Normalize text data during system migrations
- **Accessibility Tools**: Remove confusing invisible characters for screen readers

## 🧪 Testing

```bash
# Run automated smoke tests
cd cosmic-text-linter/tests
chmod +x smoke-test.sh
./smoke-test.sh

# Test with sample inputs
cat test-samples.txt  # Review 20+ Unicode attack vectors
```

## 🛠️ Technology Stack

### Backend
- **PHP 7.4+** - Core application logic
- **ICU Library** - Unicode normalization and spoofchecker
- **Apache/Nginx** - Web server with rewrite rules

### Frontend
- **Vanilla JavaScript ES6+** - No framework dependencies
- **HTML5** - Semantic markup with ARIA accessibility
- **CSS3** - Grid layout, animations, custom properties
- **Google Fonts** - Orbitron and Space Mono typefaces

### Key Dependencies
- `IntlChar` - Unicode character properties
- `Normalizer` - NFC/NFKC normalization
- `Spoofchecker` - Confusable detection (ICU)
- `Transliterator` - Script normalization

## 🔒 Security Highlights

The 16-step sanitization pipeline defends against:

1. **Trojan Source Attacks** - Bidirectional override exploits
2. **Homoglyph Spoofing** - Cyrillic/Greek lookalikes
3. **Invisible Watermarking** - Zero-width steganography
4. **TAG Character Injection** - Prompt injection via TAG block
5. **Zalgo Text** - Excessive combining marks
6. **Mixed Script Attacks** - Script confusion vulnerabilities

See [SECURITY.md](SECURITY.md) for detailed threat modeling.

## 📈 Version History

- **v2.2.1** (Current) - Production release with ICU enum resolution
- **v2.2.0** - Advisory matrix and Spoofchecker integration
- **v2.1.0** - Expanded Unicode sanitization pipeline
- **v2.0.0** - Initial release with three operation modes

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for:
- Code of conduct
- Development setup
- Coding standards
- Pull request process
- Testing requirements

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Credits

Built by **Internet Universe** ([internetuniverse.org](https://internetuniverse.org))

Inspired by Unicode security research and specifications:
- [UAX #9](https://unicode.org/reports/tr9/) - Unicode Bidirectional Algorithm
- [UTS #39](https://unicode.org/reports/tr39/) - Unicode Security Mechanisms
- [UAX #31](https://unicode.org/reports/tr31/) - Unicode Identifier and Pattern Syntax
- [UTS #51](https://unicode.org/reports/tr51/) - Unicode Emoji

## 🔗 Links

- **Documentation**: [Full docs](cosmic-text-linter/README.md)
- **Live Demo**: [Coming soon]
- **Issue Tracker**: [GitHub Issues](https://github.com/johnzfitch/definitelynot.ai/issues)
- **Discussions**: [GitHub Discussions](https://github.com/johnzfitch/definitelynot.ai/discussions)

## 📞 Support

For questions, bug reports, or feature requests:
- Open an [issue](https://github.com/johnzfitch/definitelynot.ai/issues)
- Start a [discussion](https://github.com/johnzfitch/definitelynot.ai/discussions)
- Contact: [internetuniverse.org](https://internetuniverse.org)

---

**⚠️ Production Deployment Checklist**

- [ ] Enable `mbstring` and `intl` PHP extensions
- [ ] Configure CORS headers for production domain
- [ ] Update `RewriteBase` in `.htaccess` for deployment path
- [ ] Enable SSL/TLS certificate
- [ ] Run smoke tests against production endpoint
- [ ] Configure Content Security Policy headers
- [ ] Set up monitoring and error logging
- [ ] Review and restrict file permissions (755/644)

---

<p align="center">Made with 🚀 by <a href="https://internetuniverse.org">Internet Universe</a></p>
