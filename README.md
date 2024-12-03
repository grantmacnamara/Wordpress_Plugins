# WhiskyAI - AI-Powered Whisky Description Generator

WhiskyAI is a WordPress plugin that leverages OpenAI's GPT-4 to automatically generate detailed whisky descriptions and categorize whisky products for WooCommerce stores.

## 🥃 Features

- Automatically generates detailed whisky descriptions using GPT-4
- Intelligently categorizes whiskies based on flavor profiles
- Bulk processing capabilities for your entire product catalog
- Individual product processing from the product edit page
- Progress tracking and statistics dashboard
- Customizable AI prompts
- UK English spelling optimization
- Automatic product tagging system

## 📋 Flavor Categories

The plugin uses the following predefined whisky flavor categories:

- Floral
- Fruity
- Vanilla
- Honey
- Spicy
- Peated
- Salty
- Woody
- Nutty
- Chocolatey

## 🏷️ Tagging System

The plugin uses two special tags to track processing status:

- `DescUpdated`: Indicates that a product's description has been AI-generated
- `CatUpdated`: Indicates that a product's categories have been AI-assigned

## ⚙️ Requirements

- WordPress 5.2 or higher
- PHP 7.2 or higher
- WooCommerce installed and activated
- OpenAI API key

## 🚀 Installation

1. Upload the `WhiskyAI_plugin` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the WhiskyAI settings page
4. Enter your OpenAI API key and verify it
5. Customize your prompts (optional)
6. Start generating descriptions!

## 💻 Usage

### Bulk Processing

1. Navigate to WhiskyAI in your WordPress admin menu
2. Click "Generate All Descriptions" to process your entire catalog
3. Or click "Generate Remaining Only" to process unprocessed products

### Individual Processing

1. Edit any product in WooCommerce
2. Find the WhiskyAI meta box in the sidebar
3. Click "Generate Description" or "Generate Categories"
4. Review and save the changes

## ⚡ API Settings

The plugin includes customizable prompts for both description and category generation:

### Description Prompt
Default: "You are a helpful Scottish Whisky chat assistant. You will answer in UK English spelling."

### Category Prompt
Default: "We are describing the tasting notes of Scottish Malt Whisky. Only respond using these words to describe Scottish Malt Whisky. Only use these categories to describe if appropriate. Reply only in a list."

## 📊 Progress Tracking

The plugin includes a statistics dashboard showing:
- Total number of products
- Number of processed products
- Number of remaining products
- Progress percentage

## 🛠️ Development

Built with:
- PHP
- WordPress Plugin API
- OpenAI API
- jQuery
- AJAX

## 🔒 Security

- Nonce verification for all AJAX requests
- Capability checking for administrative functions
- Sanitization of all inputs and outputs
- Secure API key storage

## 📝 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 🐛 Bug Reports

Please use the GitHub issues tab to report any bugs.

## 📧 Support

For support questions, please use the WordPress.org plugin support forums.

---

Made by 🥃 by Grant Macnamara (with a little AI help ;) )
