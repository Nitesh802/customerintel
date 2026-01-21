import React, { useState, useEffect } from 'react';
import { Copy, Check, Download, ChevronDown, ChevronRight, Users, RotateCcw, Save, Search, Plus, Trash2 } from 'lucide-react';

const CustomerIntelDashboard = () => {
  const [targetCompany, setTargetCompany] = useState('');
  const [customerCompany, setCustomerCompany] = useState('');
  const [responses, setResponses] = useState({});
  const [copiedId, setCopiedId] = useState(null);
  const [expandedSections, setExpandedSections] = useState(['phase1']);
  const [showResetModal, setShowResetModal] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [customPrompts, setCustomPrompts] = useState({});
  const [showCustomPromptForm, setShowCustomPromptForm] = useState(null);
  const [newPromptText, setNewPromptText] = useState('');

  // Load from localStorage on mount
  useEffect(() => {
    const saved = localStorage.getItem('customer-intel-data');
    if (saved) {
      const data = JSON.parse(saved);
      setTargetCompany(data.targetCompany || '');
      setCustomerCompany(data.customerCompany || '');
      setResponses(data.responses || {});
      setCustomPrompts(data.customPrompts || {});
    }
  }, []);

  // Auto-save to localStorage
  useEffect(() => {
    const data = { targetCompany, customerCompany, responses, customPrompts };
    localStorage.setItem('customer-intel-data', JSON.stringify(data));
  }, [targetCompany, customerCompany, responses, customPrompts]);

  const sections = {
    phase1: {
      title: "Phase 1: Customer Fundamentals",
      color: "bg-indigo-50 border-indigo-300",
      headerColor: "bg-indigo-600",
      time: "25 min",
      items: [
        { id: "fund-1", title: "Company Overview & Scale", prompt: "[Customer Company] revenue size employees market position ranking industry 2024 2025 growth trajectory competitive standing" },
        { id: "fund-2", title: "Business Model & Structure", prompt: "[Customer Company] business model revenue streams profit drivers organizational structure business units divisions reporting structure" },
        { id: "fund-3", title: "Geographic Footprint", prompt: "[Customer Company] locations facilities headquarters regional presence manufacturing sites distribution centers geographic expansion 2024 2025" },
        { id: "fund-4", title: "Ownership & Governance", prompt: "[Customer Company] ownership structure public private PE-backed board composition major shareholders activist investors governance changes 2024 2025" },
        { id: "fund-5", title: "Recent News & Developments", prompt: "[Customer Company] recent news announcements developments last 90 days significant events leadership changes strategic shifts" },
        { id: "fund-6", title: "Industry Context & Trends", prompt: "[Customer Company] industry trends disruption regulatory changes competitive dynamics market forces 2025 2026" },
        { id: "fund-7", title: "Customer Base Profile", prompt: "[Customer Company] customer base who they serve target markets customer concentration top customers revenue dependence" },
        { id: "fund-8", title: "Operational Model", prompt: "[Customer Company] operations supply chain manufacturing distribution model outsourcing vertical integration efficiency initiatives" }
      ]
    },
    phase2: {
      title: "Phase 2: Financial Performance & Pressures",
      color: "bg-indigo-50 border-indigo-300",
      headerColor: "bg-indigo-700",
      time: "30 min",
      items: [
        { id: "fin-1", title: "Recent Financial Performance", prompt: "[Customer Company] quarterly financial results Q1 Q2 Q3 Q4 2024 2025 revenue growth profit margins beat miss vs guidance trend analysis" },
        { id: "fin-2", title: "Stock Performance", prompt: "[Customer Company] stock price 52-week high low market cap valuation vs peers investor sentiment analyst concerns 2024 2025" },
        { id: "fin-3", title: "Profitability & Margin Pressure", prompt: "[Customer Company] profit margins EBITDA operating income margin compression expansion cost pressures pricing power gross margin trends" },
        { id: "fin-4", title: "Capital Allocation & Investments", prompt: "[Customer Company] capital allocation capex spending investment priorities budget 2025 2026 ROI capital discipline shareholder returns" },
        { id: "fin-5", title: "Debt & Financial Health", prompt: "[Customer Company] debt levels leverage ratio credit rating covenants financial flexibility liquidity debt maturity schedule refinancing" },
        { id: "fin-6", title: "Cost Reduction Initiatives", prompt: "[Customer Company] cost reduction programs efficiency initiatives restructuring layoffs headcount reductions savings targets 2024 2025 announced vs achieved" },
        { id: "fin-7", title: "M&A Activity & Divestitures", prompt: "[Customer Company] acquisitions mergers divestitures M&A strategy portfolio optimization asset sales 2024 2025 integration success deal rationale" },
        { id: "fin-8", title: "Analyst & Investor Sentiment", prompt: "[Customer Company] analyst ratings upgrades downgrades price targets investor concerns bull vs bear thesis sentiment shifts 2025" }
      ]
    },
    phase3: {
      title: "Phase 3: Leadership & Decision-Makers",
      color: "bg-indigo-50 border-indigo-300",
      headerColor: "bg-indigo-800",
      time: "35 min",
      items: [
        { id: "lead-1", title: "CEO & Executive Leadership", prompt: "[Customer Company] CEO name executive leadership team C-suite names titles tenure background recent changes" },
        { id: "lead-2", title: "CEO Priorities & Mandate", prompt: "[Customer Company] CEO priorities strategy mandate board expectations investor commitments public statements earnings call themes 2025" },
        { id: "lead-3", title: "CFO & Financial Leadership", prompt: "[Customer Company] CFO name financial leadership priorities cost control margin improvement cash generation budget authority financial strategy" },
        { id: "lead-4", title: "COO & Operations Leadership", prompt: "[Customer Company] COO VP Operations operational leadership supply chain manufacturing quality operational priorities efficiency goals" },
        { id: "lead-5", title: "Procurement & Sourcing Leadership", prompt: "[Customer Company] chief procurement officer CPO VP sourcing procurement strategy vendor consolidation cost savings supply chain resilience" },
        { id: "lead-6", title: "Department-Specific Leaders", prompt: "[Customer Company] [relevant department] VP Director leadership decision authority budget control strategic priorities team size" },
        { id: "lead-7", title: "Recent Leadership Changes", prompt: "[Customer Company] executive changes new hires departures promotions 2024 2025 turnover patterns replacement timing transition impact" },
        { id: "lead-8", title: "Board Composition & Influence", prompt: "[Customer Company] board of directors members backgrounds expertise board changes activist representation committee assignments governance influence" },
        { id: "lead-9", title: "Performance Metrics & Accountability", prompt: "[Customer Company] executive compensation KPIs performance metrics bonus structure incentive alignment announced targets vs actual results" },
        { id: "lead-10", title: "LinkedIn Profile Deep Dive", prompt: "LinkedIn search: [Customer Company] [Stakeholder Name] recent posts connections background tenure career trajectory priorities content themes engagement" },
        { id: "lead-11", title: "Stakeholder Pressure Points", prompt: "[Customer Company] [Stakeholder Name] current pressures challenges goals accountability performance expectations board scrutiny 2025" },
        { id: "lead-12", title: "Decision-Making Authority", prompt: "[Customer Company] procurement approval process decision authority budget thresholds who approves what dollar amounts signature authority" }
      ]
    },
    phase4: {
      title: "Phase 4: Strategic Initiatives & Expansion",
      color: "bg-indigo-50 border-indigo-300",
      headerColor: "bg-indigo-900",
      time: "30 min",
      items: [
        { id: "init-1", title: "Growth Initiatives & Investments", prompt: "[Customer Company] growth strategy expansion initiatives investment priorities 2025 2026 strategic focus areas resource allocation" },
        { id: "init-2", title: "Facility Expansion & Construction", prompt: "[Customer Company] new facilities construction expansion real estate development 2025 manufacturing capacity warehouse distribution" },
        { id: "init-3", title: "Hiring Plans & Headcount", prompt: "[Customer Company] hiring plans headcount growth job openings recruitment 2025 departments expanding skills sought talent acquisition" },
        { id: "init-4", title: "Technology Transformation", prompt: "[Customer Company] digital transformation technology initiatives IT modernization 2025 systems upgrades cloud migration automation" },
        { id: "init-5", title: "Product & Service Launches", prompt: "[Customer Company] new products services launches innovation pipeline 2025 R&D investments go-to-market strategy" },
        { id: "init-6", title: "Market Expansion Plans", prompt: "[Customer Company] market expansion new markets geographic growth international 2025 vertical expansion segment strategy" },
        { id: "init-7", title: "Partnerships & Alliances", prompt: "[Customer Company] strategic partnerships alliances joint ventures collaborations 2024 2025 partner ecosystem co-innovation" },
        { id: "init-8", title: "Sustainability & ESG Initiatives", prompt: "[Customer Company] sustainability ESG environmental social governance initiatives 2025 carbon reduction renewable energy DEI programs" },
        { id: "init-9", title: "Innovation Programs", prompt: "[Customer Company] innovation programs R&D research development pilot programs skunkworks innovation labs centers of excellence" },
        { id: "init-10", title: "Customer Experience Improvements", prompt: "[Customer Company] customer experience CX improvements service enhancements 2025 NPS improvements digital customer journey" }
      ]
    },
    phase5: {
      title: "Phase 5: Operational Challenges",
      color: "bg-indigo-50 border-indigo-300",
      headerColor: "bg-indigo-950",
      time: "25 min",
      items: [
        { id: "chal-1", title: "Current Operational Pain Points", prompt: "[Customer Company] operational challenges problems pain points issues 2025 bottlenecks inefficiencies execution gaps" },
        { id: "chal-2", title: "Supply Chain Challenges", prompt: "[Customer Company] supply chain issues disruptions challenges delays 2024 2025 supplier problems logistics constraints inventory" },
        { id: "chal-3", title: "Quality & Service Issues", prompt: "[Customer Company] quality issues service problems customer complaints 2024 2025 defects recalls warranty claims satisfaction scores" },
        { id: "chal-4", title: "Talent & Labor Challenges", prompt: "[Customer Company] labor shortage hiring challenges retention turnover workforce availability skills gaps training needs" },
        { id: "chal-5", title: "Technology & Systems Issues", prompt: "[Customer Company] technology issues system problems IT challenges legacy systems technical debt integration problems" },
        { id: "chal-6", title: "Regulatory & Compliance Pressures", prompt: "[Customer Company] regulatory challenges compliance issues government regulations 2025 policy changes enforcement actions fines" },
        { id: "chal-7", title: "Capacity & Scalability Constraints", prompt: "[Customer Company] capacity constraints scalability challenges growth limitations bottlenecks utilization rates capex needs" },
        { id: "chal-8", title: "Competitive Threats", prompt: "[Customer Company] competitive threats pressure market share loss challenges from competitors new entrants disruptors" }
      ]
    },
    phase6: {
      title: "Phase 6: Technology & Systems",
      color: "bg-indigo-50 border-indigo-300",
      headerColor: "bg-purple-900",
      time: "20 min",
      items: [
        { id: "tech-1", title: "Current Technology Stack", prompt: "[Customer Company] technology stack systems platforms software infrastructure ERP CRM WMS MES applications" },
        { id: "tech-2", title: "Recent Technology Investments", prompt: "[Customer Company] technology investments IT spending system implementations 2024 2025 software purchases infrastructure upgrades" },
        { id: "tech-3", title: "Digital Transformation Initiatives", prompt: "[Customer Company] digital transformation digitalization automation initiatives RPA AI ML IoT connected systems" },
        { id: "tech-4", title: "Data & Analytics Capabilities", prompt: "[Customer Company] data analytics business intelligence AI machine learning capabilities data infrastructure data lakes warehouses" },
        { id: "tech-5", title: "Integration & API Strategy", prompt: "[Customer Company] integration strategy APIs system connectivity interoperability middleware integration platforms iPaaS" },
        { id: "tech-6", title: "Technology Leadership & Vision", prompt: "[Customer Company] CTO CIO technology leadership IT strategy vision digital roadmap technology priorities" }
      ]
    },
    phase7: {
      title: "Phase 7: Competitive Dynamics",
      color: "bg-indigo-50 border-indigo-300",
      headerColor: "bg-violet-900",
      time: "25 min",
      items: [
        { id: "comp-1", title: "Market Position & Share", prompt: "[Customer Company] market share position ranking competitors 2024 2025 industry standing market leader challenger follower" },
        { id: "comp-2", title: "Main Competitors", prompt: "[Customer Company] main competitors competitive landscape top rivals head-to-head competition direct substitutes" },
        { id: "comp-3", title: "Competitive Advantages", prompt: "[Customer Company] competitive advantages differentiators strengths unique capabilities what they do better than competitors" },
        { id: "comp-4", title: "Competitive Vulnerabilities", prompt: "[Customer Company] competitive weaknesses vulnerabilities disadvantages challenges where competitors have advantage exploitable weaknesses" },
        { id: "comp-5", title: "Win/Loss Patterns", prompt: "[Customer Company] competitive wins losses market battles customer acquisition customer defections win rates loss reasons competitive displacement" },
        { id: "comp-6", title: "Competitive Response Strategy", prompt: "[Customer Company] competitive strategy response to competitors differentiation positioning 2025 proactive vs reactive speed of response effectiveness" },
        { id: "comp-7", title: "Pricing Pressure & Dynamics", prompt: "[Customer Company] pricing pressure competition price wars discounting margin compression pricing power value vs price positioning customer sensitivity" },
        { id: "comp-8", title: "Emerging Competitive Threats", prompt: "[Customer Company] emerging competitors disruptors new entrants threats 2025 technology substitutes business model innovation market disruption" }
      ]
    },
    phase8: {
      title: "Phase 8: Relationship with Target Company",
      color: "bg-indigo-50 border-indigo-300",
      headerColor: "bg-blue-900",
      time: "30 min",
      items: [
        { id: "rel-1", title: "Current Contract Status", prompt: "[Customer Company] [Target Company] current contract partnership status contract value duration renewal date scope terms performance metrics" },
        { id: "rel-2", title: "Historical Relationship", prompt: "[Customer Company] [Target Company] relationship history timeline partnership evolution contract renewals expansions contractions relationship health trajectory" },
        { id: "rel-3", title: "Spend & Volume Analysis", prompt: "[Customer Company] spending with [Target Company] volume revenue purchasing patterns share of wallet trend growth declining historical spend benchmarks" },
        { id: "rel-4", title: "Service/Product Coverage", prompt: "[Customer Company] using [Target Company] products services coverage scope penetration usage intensity cross-sell opportunities gaps white space" },
        { id: "rel-5", title: "Key Relationship Contacts", prompt: "[Customer Company] [Target Company] relationship stakeholders account team contacts decision makers influencers relationship strength tenure turnover" },
        { id: "rel-6", title: "Satisfaction & Performance", prompt: "[Customer Company] satisfaction with [Target Company] performance issues service quality complaints escalations NPS advocacy at-risk indicators" },
        { id: "rel-7", title: "Competitive Vendor Relationships", prompt: "[Customer Company] relationships with [Target's competitors] alternative vendors competitive contracts comparison preferred vendors switching risk" },
        { id: "rel-8", title: "Strategic vs Transactional", prompt: "[Customer Company] [Target Company] partnership classification strategic vendor preferred supplier transactional commodity relationship depth executive engagement" },
        { id: "rel-9", title: "Procurement Process & Cadence", prompt: "[Customer Company] procurement process vendor evaluation RFP cycles budget planning approval workflow decision timeline purchasing patterns seasonality" },
        { id: "rel-10", title: "Recent Interactions & Communications", prompt: "[Customer Company] [Target Company] recent communications announcements joint initiatives press releases case studies testimonials partnership news events" }
      ]
    },
    phase9: {
      title: "Phase 9: Timing & Catalysts",
      color: "bg-indigo-50 border-indigo-300",
      headerColor: "bg-cyan-900",
      time: "20 min",
      items: [
        { id: "time-1", title: "Budget Cycles & Planning", prompt: "[Customer Company] budget cycle fiscal year planning process annual planning budget approval timeline budget freeze purchasing windows Q1 Q2 Q3 Q4" },
        { id: "time-2", title: "Contract Renewal Dates", prompt: "[Customer Company] [Target Company] contract renewal date expiration timeline negotiation window auto-renewal terms termination notice requirements decision timing" },
        { id: "time-3", title: "Strategic Planning Windows", prompt: "[Customer Company] strategic planning cycle annual planning process timeline decision windows board meetings investor days planning season timing" },
        { id: "time-4", title: "Implementation Timelines", prompt: "[Customer Company] implementation timeline launch dates milestones go-live deadlines critical path dependencies urgency factors time pressure" },
        { id: "time-5", title: "Industry Events & Deadlines", prompt: "[Customer Company] industry events conferences trade shows regulatory deadlines compliance dates reporting requirements fiscal events 2025 2026" },
        { id: "time-6", title: "Decision Windows", prompt: "[Customer Company] decision making process approval timeline vendor selection evaluation schedule RFP schedule decision urgency compressed timelines forcing events" }
      ]
    }
  };

  const toggleSection = (section) => {
    setExpandedSections(prev =>
      prev.includes(section)
        ? prev.filter(s => s !== section)
        : [...prev, section]
    );
  };

  const copyToClipboard = (text, id) => {
    let populatedText = text
      .replace(/\[Target Company\]/g, targetCompany || '[Target Company]')
      .replace(/\[Customer Company\]/g, customerCompany || '[Customer Company]');
    navigator.clipboard.writeText(populatedText);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  const updateResponse = (id, value) => {
    setResponses(prev => ({ ...prev, [id]: value }));
  };

  const addCustomPrompt = (sectionKey) => {
    if (!newPromptText.trim()) return;
    
    const customId = `custom-${sectionKey}-${Date.now()}`;
    setCustomPrompts(prev => ({
      ...prev,
      [sectionKey]: [...(prev[sectionKey] || []), {
        id: customId,
        title: "Custom Prompt",
        prompt: newPromptText
      }]
    }));
    setNewPromptText('');
    setShowCustomPromptForm(null);
  };

  const deleteCustomPrompt = (sectionKey, promptId) => {
    setCustomPrompts(prev => ({
      ...prev,
      [sectionKey]: prev[sectionKey].filter(p => p.id !== promptId)
    }));
    setResponses(prev => {
      const updated = { ...prev };
      delete updated[promptId];
      return updated;
    });
  };

  const calculateProgress = () => {
    const allItems = Object.values(sections).flatMap(s => s.items);
    const customItems = Object.values(customPrompts).flat();
    const total = allItems.length + customItems.length;
    const completed = [...allItems, ...customItems].filter(item => 
      responses[item.id]?.trim()
    ).length;
    return { completed, total, percentage: Math.round((completed / total) * 100) };
  };

  const progress = calculateProgress();

  const exportToMarkdown = () => {
    let markdown = `# CUSTOMER INTELLIGENCE PLAYBOOK\n`;
    markdown += `## ${customerCompany || '[Customer]'} - ${targetCompany || '[Target]'}\n\n`;
    markdown += `**Research Date:** ${new Date().toLocaleDateString()}\n`;
    markdown += `**Purpose:** Deep expansion opportunities and strategic positioning\n`;
    markdown += `**Target Company:** ${targetCompany}\n`;
    markdown += `**Customer Company:** ${customerCompany}\n\n---\n\n`;

    Object.entries(sections).forEach(([key, section]) => {
      markdown += `## ${section.title.toUpperCase()}\n\n`;
      
      const allItems = [...section.items, ...(customPrompts[key] || [])];
      allItems.forEach(item => {
        if (responses[item.id]?.trim()) {
          markdown += `### ${item.title}\n\n`;
          markdown += `**Research Query:** ${item.prompt}\n\n`;
          markdown += `${responses[item.id]}\n\n---\n\n`;
        }
      });
    });

    const blob = new Blob([markdown], { type: 'text/markdown' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${customerCompany.replace(/\s+/g, '_')}_Intelligence_${new Date().toISOString().split('T')[0]}.md`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  const exportForNotebookLM = () => {
    let content = `CUSTOMER INTELLIGENCE - ${customerCompany || '[Customer]'} for ${targetCompany || '[Target]'}\n`;
    content += `Research Date: ${new Date().toLocaleDateString()}\n`;
    content += `\n${'='.repeat(80)}\n\n`;

    Object.entries(sections).forEach(([key, section]) => {
      content += `${section.title.toUpperCase()}\n`;
      content += `${'-'.repeat(section.title.length)}\n\n`;
      
      const allItems = [...section.items, ...(customPrompts[key] || [])];
      allItems.forEach(item => {
        if (responses[item.id]?.trim()) {
          content += `${item.title}\n\n`;
          content += `${responses[item.id]}\n\n`;
          content += `${'~'.repeat(80)}\n\n`;
        }
      });
      content += `\n`;
    });

    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${customerCompany.replace(/\s+/g, '_')}_NotebookLM_${new Date().toISOString().split('T')[0]}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  const handleSaveAndReset = () => {
    exportToMarkdown();
    setTimeout(() => {
      localStorage.removeItem('customer-intel-data');
      setTargetCompany('');
      setCustomerCompany('');
      setResponses({});
      setCustomPrompts({});
      setShowResetModal(false);
    }, 500);
  };

  const handleResetWithoutSave = () => {
    localStorage.removeItem('customer-intel-data');
    setTargetCompany('');
    setCustomerCompany('');
    setResponses({});
    setCustomPrompts({});
    setShowResetModal(false);
  };

  const filteredSections = searchTerm ? 
    Object.entries(sections).reduce((acc, [key, section]) => {
      const allItems = [...section.items, ...(customPrompts[key] || [])];
      const filtered = allItems.filter(item =>
        item.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.prompt.toLowerCase().includes(searchTerm.toLowerCase())
      );
      if (filtered.length > 0) {
        acc[key] = { ...section, items: filtered };
      }
      return acc;
    }, {}) : sections;

  return (
    <div className="w-full max-w-7xl mx-auto p-6 bg-gradient-to-br from-indigo-50 to-purple-50 min-h-screen">
      {/* Header */}
      <div className="bg-white rounded-xl shadow-lg p-8 mb-6 border-t-4 border-indigo-600">
        <div className="flex items-start justify-between mb-6">
          <div className="flex-1">
            <h1 className="text-4xl font-bold text-gray-900 mb-2">Customer Intelligence Dashboard</h1>
            <p className="text-lg text-gray-600">Deep research for expansion opportunities and strategic positioning</p>
          </div>
          <Users className="text-indigo-600" size={48} />
        </div>

        {/* Company Inputs */}
        <div className="grid md:grid-cols-2 gap-4 mb-6">
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Target Company (Your Company):
            </label>
            <input
              type="text"
              value={targetCompany}
              onChange={(e) => setTargetCompany(e.target.value)}
              placeholder="e.g., Fastenal"
              className="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-lg"
            />
          </div>
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
              Customer Company (Target's Customer):
            </label>
            <input
              type="text"
              value={customerCompany}
              onChange={(e) => setCustomerCompany(e.target.value)}
              placeholder="e.g., Wabash National"
              className="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-lg"
            />
          </div>
        </div>

        {/* Progress Stats */}
        <div className="grid grid-cols-4 gap-4 mb-6">
          <div className="bg-indigo-50 rounded-lg p-4 text-center">
            <div className="text-2xl font-bold text-indigo-600">{progress.completed}</div>
            <div className="text-sm text-gray-600">Completed</div>
          </div>
          <div className="bg-purple-50 rounded-lg p-4 text-center">
            <div className="text-2xl font-bold text-purple-600">{progress.total}</div>
            <div className="text-sm text-gray-600">Total Prompts</div>
          </div>
          <div className="bg-violet-50 rounded-lg p-4 text-center">
            <div className="text-2xl font-bold text-violet-600">{progress.percentage}%</div>
            <div className="text-sm text-gray-600">Progress</div>
          </div>
          <div className="bg-indigo-50 rounded-lg p-4 text-center">
            <div className="text-2xl font-bold text-indigo-600">6-8h</div>
            <div className="text-sm text-gray-600">Est. Time</div>
          </div>
        </div>

        {/* Progress Bar */}
        <div className="w-full bg-gray-200 rounded-full h-4 overflow-hidden mb-6">
          <div
            className="bg-gradient-to-r from-indigo-600 to-purple-600 h-4 rounded-full transition-all duration-500"
            style={{ width: `${progress.percentage}%` }}
          />
        </div>

        {/* Search */}
        <div className="relative mb-4">
          <Search className="absolute left-3 top-3 text-gray-400" size={20} />
          <input
            type="text"
            placeholder="Search across 76+ prompts..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        {/* Action Buttons */}
        <div className="flex gap-3">
          <button
            onClick={exportToMarkdown}
            className="flex-1 bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 font-semibold"
          >
            <Download size={18} />
            Full Markdown
          </button>
          <button
            onClick={exportForNotebookLM}
            className="flex-1 bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition-all flex items-center justify-center gap-2 font-semibold"
          >
            <Download size={18} />
            NotebookLM Format
          </button>
          <button
            onClick={() => setShowResetModal(true)}
            className="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-all flex items-center justify-center gap-2 font-semibold"
          >
            <RotateCcw size={18} />
            Start New
          </button>
        </div>
      </div>

      {/* Prompt Sections */}
      {Object.entries(filteredSections).map(([key, section]) => {
        const allItems = [...section.items, ...(customPrompts[key] || [])];
        
        return (
          <div key={key} className={`bg-white rounded-xl shadow-lg mb-6 overflow-hidden border-2 ${section.color}`}>
            <button
              onClick={() => toggleSection(key)}
              className={`w-full ${section.headerColor} text-white px-6 py-4 flex items-center justify-between hover:opacity-90 transition-all`}
            >
              <div className="flex items-center gap-3">
                {expandedSections.includes(key) ? <ChevronDown size={24} /> : <ChevronRight size={24} />}
                <div className="text-left">
                  <h2 className="text-xl font-bold">{section.title}</h2>
                  <p className="text-sm opacity-90">{allItems.length} prompts</p>
                </div>
              </div>
              <span className="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm font-semibold">{section.time}</span>
            </button>

            {expandedSections.includes(key) && (
              <div className="p-6">
                <div className="space-y-6">
                  {allItems.map((item) => (
                    <div key={item.id} className="bg-white border-2 border-gray-200 rounded-lg p-6 hover:border-indigo-300 transition-all">
                      <div className="flex items-start justify-between mb-4">
                        <h3 className="text-lg font-bold text-gray-900 flex-1">{item.title}</h3>
                        <div className="flex items-center gap-2">
                          {item.id.startsWith('custom-') && (
                            <button
                              onClick={() => deleteCustomPrompt(key, item.id)}
                              className="text-red-600 hover:bg-red-50 px-2 py-1 rounded transition-all"
                            >
                              <Trash2 size={16} />
                            </button>
                          )}
                          <button
                            onClick={() => copyToClipboard(item.prompt, item.id)}
                            className="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-all flex items-center gap-2 text-sm font-semibold"
                          >
                            {copiedId === item.id ? (
                              <>
                                <Check size={16} />
                                Copied!
                              </>
                            ) : (
                              <>
                                <Copy size={16} />
                                Copy
                              </>
                            )}
                          </button>
                        </div>
                      </div>

                      <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-4">
                        <p className="text-sm text-gray-800 leading-relaxed font-mono">
                          {item.prompt
                            .replace(/\[Target Company\]/g, targetCompany || '[Target Company]')
                            .replace(/\[Customer Company\]/g, customerCompany || '[Customer Company]')}
                        </p>
                      </div>

                      <div className="space-y-2">
                        <label className="block text-sm font-semibold text-gray-700">
                          Paste Perplexity Response:
                        </label>
                        <textarea
                          placeholder="Paste research findings here..."
                          value={responses[item.id] || ''}
                          onChange={(e) => updateResponse(item.id, e.target.value)}
                          className="w-full h-40 p-4 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-y text-sm"
                        />
                        {responses[item.id]?.trim() && (
                          <div className="flex items-center gap-2 text-green-600 text-sm font-semibold">
                            <Check size={16} />
                            Saved ({responses[item.id].length} characters)
                          </div>
                        )}
                      </div>
                    </div>
                  ))}

                  {/* Add Custom Prompt */}
                  <div className="border-2 border-dashed border-gray-300 rounded-lg p-6">
                    {showCustomPromptForm === key ? (
                      <div className="space-y-3">
                        <textarea
                          placeholder="Enter your custom prompt..."
                          value={newPromptText}
                          onChange={(e) => setNewPromptText(e.target.value)}
                          className="w-full h-24 p-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                        />
                        <div className="flex gap-2">
                          <button
                            onClick={() => addCustomPrompt(key)}
                            className="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-all font-semibold"
                          >
                            Add Prompt
                          </button>
                          <button
                            onClick={() => {
                              setShowCustomPromptForm(null);
                              setNewPromptText('');
                            }}
                            className="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition-all font-semibold"
                          >
                            Cancel
                          </button>
                        </div>
                      </div>
                    ) : (
                      <button
                        onClick={() => setShowCustomPromptForm(key)}
                        className="w-full flex items-center justify-center gap-2 text-indigo-600 hover:text-indigo-700 font-semibold"
                      >
                        <Plus size={20} />
                        Add Custom Prompt
                      </button>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>
        );
      })}

      {/* Reset Modal */}
      {showResetModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
            <h3 className="text-xl font-bold text-gray-900 mb-4">Start New Research?</h3>
            <p className="text-gray-600 mb-6">
              Would you like to save your current research before starting new?
            </p>
            <div className="flex flex-col gap-3">
              <button
                onClick={handleSaveAndReset}
                className="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 font-semibold"
              >
                <Save size={18} />
                Save & Start New
              </button>
              <button
                onClick={handleResetWithoutSave}
                className="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-all flex items-center justify-center gap-2 font-semibold"
              >
                <RotateCcw size={18} />
                Start New Without Saving
              </button>
              <button
                onClick={() => setShowResetModal(false)}
                className="bg-gray-300 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-400 transition-all font-semibold"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default CustomerIntelDashboard;