# CustomerIntel for Moodle

**Version:** 1.0.0  
**Requires:** Moodle 4.0+  
**License:** GPL v3 or later

## Overview

CustomerIntel is a powerful Moodle plugin that leverages AI-powered research notebooks to generate comprehensive customer intelligence reports. It automates the analysis of companies and targets across 15 specialized dimensions, providing actionable insights for sales, marketing, and customer success teams.

## Features

### 🤖 AI-Powered Analysis
- 15 specialized research notebooks covering all aspects of customer intelligence
- Multi-provider LLM support (OpenAI, Claude, local models)
- Intelligent token management with caching and reuse

### 📊 Comprehensive Reporting
- Multi-format export: HTML, PDF, JSON
- Executive summaries and detailed analysis
- Visual dashboards with key metrics

### 🔮 Predictive Insights & Analytics
- **Forecasting**: AI-powered trend prediction up to 90 days ahead
- **Anomaly Detection**: Automated identification of performance deviations
- **Risk Assessment**: Intelligent prioritization of potential issues
- **Early Warning System**: Proactive alerts for developing problems
- **Statistical Analysis**: Z-score-based anomaly classification with confidence intervals

### 🔄 Version Control
- Snapshot creation for all analyses
- Diff engine for tracking changes over time
- Historical comparison capabilities

### 💰 Cost Management
- Real-time token usage tracking
- Cost calculation per analysis
- Budget monitoring and alerts

### ⚡ Performance
- Async job processing for long operations
- P95 runtime < 15 minutes
- Efficient caching (25-40% token reuse)

## Installation

1. Download the plugin from the [Moodle plugins directory](https://moodle.org/plugins) or [GitHub releases](https://github.com/yourorg/customerintel/releases)
2. Extract to `/local/customerintel/`
3. Visit Site Administration > Notifications to install
4. Configure API keys in plugin settings

## Quick Start

### 1. Configure API Keys
Navigate to: **Site Administration > Plugins > Local plugins > CustomerIntel**

Add your LLM provider API keys:
- OpenAI API Key
- Claude API Key (optional)
- Local model endpoint (optional)

### 2. Set Capabilities
Assign capabilities to roles:
- `local/customerintel:view` - View reports
- `local/customerintel:manage` - Create/edit analyses
- `local/customerintel:export` - Export reports

### 3. Create Your First Analysis
1. Go to **Navigation > CustomerIntel**
2. Click "Add Company"
3. Enter company details
4. Add data sources
5. Click "Run Analysis"

## Research Notebooks

CustomerIntel performs analysis across 15 dimensions:

1. **Industry Analysis** - Market dynamics and trends
2. **Company Analysis** - Business model and operations
3. **Market Position** - Competitive standing
4. **Customer Base** - Target audience profiling
5. **Growth Trajectory** - Historical and projected growth
6. **Tech Stack** - Technology infrastructure
7. **Integration Landscape** - System connectivity
8. **Strategic Initiatives** - Key business priorities
9. **Challenges** - Pain points and obstacles
10. **Competitive Landscape** - Market competition
11. **Financial Health** - Economic indicators
12. **Decision Makers** - Key stakeholder mapping
13. **Buying Process** - Procurement patterns
14. **Value Proposition Alignment** - Solution fit analysis
15. **Engagement Strategy** - Recommended approach

## Requirements

### System Requirements
- PHP 7.4 or higher
- Moodle 4.0 or higher
- MySQL 5.7+ / MariaDB 10.2+ / PostgreSQL 10+
- 256MB memory limit recommended

### Optional Components
- PDF library (TCPDF/DOMPDF) for PDF export
- Cron configuration for background jobs

## Configuration

### Cron Setup
Add to your Moodle cron for background processing:
```
*/5 * * * * php /path/to/moodle/local/customerintel/cli/process_queue.php
```

### Performance Tuning
- Adjust token cache TTL in settings
- Configure concurrent job limits
- Set rate limits for API calls

## CLI Tools

### Test Integration
```bash
php local/customerintel/cli/test_integration.php
```

### Schema Check
```bash
php local/customerintel/cli/check_schema_consistency.php
```

### Pre-deployment Check
```bash
php local/customerintel/cli/pre_deploy_check.php
```

## Support

### Documentation
- [Testing Guide](docs/TESTING_GUIDE.md)
- [Technical Specifications](docs/TECHNICAL_SPEC.md)
- [API Documentation](docs/API.md)

### Getting Help
- [Issue Tracker](https://github.com/yourorg/customerintel/issues)
- [Community Forum](https://moodle.org/mod/forum/view.php?id=xxxx)
- [Documentation Wiki](https://docs.moodle.org/customerintel)

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.

## Credits

Developed by Your Organization  
Maintained by the CustomerIntel Team

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes.

---

**Latest Version:** 1.0.0 (2025-01-13)  
**Maturity:** Stable  
**Moodle Versions:** 4.0, 4.1, 4.2, 4.3