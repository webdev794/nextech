<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Seller policy page shown in Seller Center (seller_footer placement). */
    public function up(): void
    {
        if (DB::table('pages')->where('slug', 'global-data-protection-exhibit')->exists()) {
            return;
        }

        DB::table('pages')->insert([
            'slug' => 'global-data-protection-exhibit',
            'title' => 'Global Data Protection Exhibit',
            'content' => self::CONTENT,
            'is_published' => true,
            'show_in_footer' => false,
            'footer_group' => 'legal',
            'menu_placements' => json_encode(['seller_footer']),
            'sort_order' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'global-data-protection-exhibit')->delete();
    }

    private const CONTENT = <<<'MD'
_Release date: September 24, 2026_

This Global Data Protection Exhibit ("Data Protection Exhibit") forms part of the NexTech Seller Services Agreement entered into between NexTech ("NexTech") and the Seller using the NexTech Platform (as defined in the Agreement) ("Seller") (each a "Party" and together the "Parties") (NexTech Seller Services Agreement together with the Data Protection Exhibit, collectively referred to as the "Agreement"), under which the Seller agrees to undertake various activities in connection with its sales and promotions on the NexTech Platform (the "Services").

For the purposes of this Data Protection Exhibit, and except where indicated otherwise, the term "NexTech" means NexTech and shall include its Affiliates, if and to the extent the other Party processes Personal Data in connection with this Agreement for which any such Affiliate qualifies as a data controller. All capitalised terms that are not expressly defined in this Data Protection Exhibit will have the meanings given to them in the Agreement.

For the purposes of performing the Agreement, Seller may have access to, or be provided with, Personal Data that is subject to Data Protection Laws and in relation to which either Party is subject to certain obligations. This Data Protection Exhibit assists the Parties in complying with their obligations when providing or allowing access to Personal Data.

In consideration of the mutual promises set out in this Data Protection Exhibit, the Parties agree as follows:

## 1. Definitions

1.1 For the purposes of this Data Protection Exhibit:

- "Affiliate" means, in relation to an entity, another entity from time to time Controlling, Controlled by, or under common Control with that entity. For the purposes of this definition, "Control" means, with regard to an entity, the legal, beneficial or equitable ownership, directly or indirectly, of 50% or more of the capital stock (or other ownership interest, if not a corporation) of such entity ordinarily having voting rights, or the equivalent rights under contract, to control management decisions with regard to relevant subjects, and "Controlled" and "Controlling" will have corresponding meanings.
- "C-to-C Transfer Clauses" means Sections I, II, III and IV (as applicable) in so far as they relate to Module One (Controller-to-Controller) within the Standard Contractual Clauses for the transfer of Personal Data to third countries pursuant to Regulation (EU) 2016/679 of the European Parliament and the Council approved by Commission Implementing Decision (EU) 2021/914 of 4 June 2021.
- "C-to-P Transfer Clauses" means the Sections I, II, III and IV (as applicable) in so far as they relate to Module Two (Controller-to-Processor) within the Standard Contractual Clauses for the transfer of Personal Data to third countries pursuant to Regulation (EU) 2016/679 of the European Parliament and the Council approved by Commission Implementing Decision (EU) 2021/914 of 4 June 2021.
- "Data Protection Laws" means all laws and regulations that apply to the processing of Personal Data under the Agreement as amended from time to time, including, but not limited to, the GDPR, the Data Protection Act 2018, any successor thereto, and any applicable laws and regulations of the United Kingdom ("UK"), United States and its states, Switzerland, Japan, Korea, European Union and its member states.
- "Data Subject Request" means an actual or purported request, notice, or complaint from (or on behalf of) a data subject exercising his or her rights under Data Protection Laws.
- "Information Security Incident" means the accidental or unlawful destruction, loss, alteration, unauthorized disclosure of, or access to Personal Data transmitted, stored or otherwise processed by Seller or its subprocessor. Information Security Incidents do not include unsuccessful attempts or activities that do not compromise the security of Personal Data, including unsuccessful log-in attempts, pings, port scans, denial of service attacks, or other network attacks on firewalls or networked systems.
- "Personal Data" means any information relating to an identified or identifiable natural person or household. "Personal Data" shall include analogous terms under Data Protection Laws that is transferred from (or made available by) NexTech to Seller in connection with the Agreement where each Party acts as a data controller.
- "Regulator" means any independent public authority, including any regulator or supervisory authority, established under the laws of any applicable jurisdiction responsible for the monitoring and application of Data Protection Laws.
- "Regulator Correspondence" means any correspondence or communication received from a Regulator relating to Personal Data.
- "Third-Party Request" means a written request from any third party for the disclosure of Personal Data, where compliance with such a request is required or purported to be required by applicable law or regulation.
- "special categories of data", "process/processing", "controller", "processor", "data subject" and "supervisory authority" shall have the same meaning as in the GDPR, and shall include analogous terms under Data Protection Laws.
- "GDPR" means Regulation (EU) 2016/679 of the European Parliament and the Council (General Data Protection Regulation) including as implemented or adopted under the laws of the United Kingdom.
- "subprocessor" means any processor engaged by Seller or by any other subprocessor of the Seller, which receives Personal Data from the Seller in connection with the Agreement.

1.2 In this Data Protection Exhibit:

(a) a reference to a Clause, Schedule or Appendix is, unless stated otherwise, a reference to a Clause, Schedule or Appendix to this Data Protection Exhibit; and

(b) unless the context otherwise requires, words in the singular shall include the plural and in the plural shall include the singular.

## 2. Details of the processing activities & Processing Obligations

2.1 NexTech may provide Personal Data to Seller for processing in connection with this Agreement. The subject matter of the data processing is to fulfil the Purposes and the processing will be carried out for the duration of the Agreement. Appendix 1 of the Data Protection Exhibit, as applicable, provides details of processing. Notwithstanding any contrary provision in this Data Protection Exhibit, NexTech shall be permitted to make amendments to the details of processing provided in Appendix 1 on written notice to Seller.

2.2 In relation to the Personal Data, each Party acts as an independent controller of the Personal Data processed under this Agreement. The Parties agree that they do not act as joint controllers in this regard.

2.3 Each Party shall comply with this Data Protection Exhibit and their respective obligations as independent controllers under Data Protection Laws when processing Personal Data in connection with the Agreement, and neither Party shall do or omit to do anything which places the other Party in breach of any Data Protection Laws. This Data Protection Exhibit is in addition to, and does not relieve, remove, or replace, a Party's obligations or rights under Data Protection Laws.

2.4 Personal Data can only be processed by the Seller for the Purposes.

2.5 Personal Data received pursuant to the Agreement shall be segregated from all other Personal Data processed by Seller.

2.6 Seller shall not:

(a) sell any Personal Data;

(b) retain, use, share or disclose any Personal Data for any purpose other than for the Purposes;

(c) use Personal Data for profiling, targeting, analytics, or data harvesting;

(d) do anything to cause NexTech to be in breach of Data Protection Laws; or

(e) combine Personal Data received pursuant to the Agreement with Personal Data (i) received from or on behalf of another person, or (ii) collected from Seller's own interaction with any data subject to whom such Personal Data pertains, except as and to the extent necessary as a part of Seller's performance of the Purposes.

## 3. Transparency Obligations

Unless agreed otherwise between NexTech and Seller, NexTech shall be responsible for:

3.1 providing the relevant data subjects with any notice and information required by Data Protection Laws; and

3.2 procuring all consents or rights from data subjects as are necessary in order for the Parties' processing of Personal Data to comply with Data Protection Law.

## 4. Data Security; Mutual Cooperation

4.1 Seller shall implement and maintain reasonable technical, administrative, and physical safeguards to ensure a level of security appropriate to the risk associated with the processing activity as required by Data Protection Laws, including, at a minimum, the measures described in Appendix 2 (the "Security Measures"). Seller may update the Security Measures from time to time, so long as the updated measures do not decrease the overall protection of Personal Data.

4.2 If Seller suffers or suspects an Information Security Incident in relation to Personal Data, Seller shall comply with its obligations under Data Protection Laws, including to report such an Information Security Incident to a Regulator or to data subjects. Seller shall notify NexTech promptly and without undue delay upon becoming aware of any Information Security Incident in relation to Personal Data.

4.3 Each Party shall promptly (and without undue delay) notify the other Party in the event that it receives a Data Subject Request in relation to the processing of Personal Data under, or in connection with, the Agreement. The Party that receives such a Data Subject Request shall comply with its respective obligations under Data Protection Laws and shall be responsible for responding to such request, but each Party shall provide reasonable assistance to the other Party in complying with its respective obligations under Data Protection Laws.

4.4 Each Party shall promptly (and without undue delay) notify the other relevant Party in the event that they receive any Regulator Correspondence or Third-Party Request in relation to the processing of Personal Data under, or in connection with, the Agreement. Unless otherwise agreed in writing by the Parties, the Party that receives any such Regulator Correspondence or Third-Party Request shall be responsible for responding to such request, but each Party shall provide reasonable assistance to the other Party in complying with their respective obligations under Data Protection Laws.

## 5. Subprocessors

5.1 Information about Seller's current subprocessors, including their functions and locations, is available in Appendix 4 to this Data Protection Exhibit.

5.2 When Seller engages a subprocessor that it determines to be necessary for the processing of Personal Data for the Purposes, Seller shall ensure that it does so in a manner compliant with Data Protection Laws, including, in particular, GDPR Article 28 (where applicable).

5.3 When Seller engages any new subprocessor, other than those listed at Appendix 4 to this Data Protection Exhibit, after the effective date of the Agreement, Seller will notify NexTech in writing of the proposed engagement (including the name and location of the relevant subprocessor and the activities it will perform). If NexTech objects to such engagement in a written notice to Seller within thirty (30) days after being informed of the engagement on reasonable grounds relating to the protection of Personal Data, NexTech and Seller will work together in good faith to find a mutually acceptable resolution to address such objection. If the Parties are unable to reach a mutually acceptable resolution within a reasonable timeframe, which shall not exceed thirty (30) days from the date on which NexTech raises its objection, NexTech may terminate this Agreement by providing written notice to Seller.

5.4 Seller shall remain fully liable to NexTech for any subprocessors' processing of Personal Data.

## 6. Jurisdiction-Specific Provisions; Transfer Clauses

6.1 The Parties will comply with the provisions of Appendix 3 to this Data Protection Exhibit, to the extent required by Data Protection Laws. In the event of any conflict between any applicable provisions of Appendix 3 and the Data Protection Exhibit, the applicable provisions in Appendix 3 will prevail. In the event that Data Protection Laws require additional or different terms to be executed between the Parties, NexTech may, by providing notice to Seller, amend Appendix 3 where such amendments are reasonably necessary to address the requirements of Data Protection Laws. Upon receipt of notice under this Section 6.1, Seller shall have thirty (30) days to submit to NexTech a written objection to the proposed amendment on reasonable grounds, otherwise the proposed amendment shall be deemed effective between the Parties.

6.2 Subject to Section 6.1, in the event that the C-to-C Transfer Clauses in Appendix 3 are amended, replaced, or repealed by the European Commission, the United Kingdom, or under Data Protection Laws, the Parties shall work together in good faith to enter into an updated version of the C-to-C Transfer Clauses (to the extent required), or negotiate in good faith a solution to enable a transfer of Personal Data to be conducted in compliance with Data Protection Laws.

6.3 The C-to-C Transfer Clauses will not apply to transfers of Personal Data where Seller has adopted an alternative recognized compliance mechanism for the lawful transfer of such Personal Data, such as the EU-U.S. Data Privacy Framework, the Swiss-U.S. Data Privacy Framework, or the UK Extension to the EU-U.S. Data Privacy Framework, as applicable and to the extent valid ("Data Privacy Framework"). Where Seller has a valid certification to the applicable Data Privacy Framework, the Parties agree that such transfer will be made in reliance on the Data Privacy Framework and that Seller will process Personal Data in compliance with the Data Privacy Framework principles.

6.4 Seller warrants and undertakes that it shall not transfer, nor allow its subprocessors to transfer, Personal Data outside of the European Union, European Economic Area, the UK or Switzerland, unless it has specific authorization from NexTech to do so. For transfers of Personal Data under the Agreement by Seller or its subprocessors from the European Union, European Economic Area, the UK or Switzerland to countries that do not ensure an adequate level of data protection within the meaning of Data Protection Laws (which, for the avoidance of doubt, may include transfers from the European Economic Area to the UK), Seller acknowledges and agrees that Seller has implemented, and will implement, all transfer mechanisms required to comply with Data Protection Laws and shall ensure such compliance by its subprocessors, including entering into, or procuring that such subprocessors enter into, the C-to-P Transfer Clauses.

6.5 Seller will provide NexTech reasonable support to enable NexTech's compliance with the requirements imposed on international transfers of Personal Data. Seller will, upon NexTech's request, provide information to NexTech that is reasonably necessary for NexTech to complete a transfer impact assessment ("TIA") to the extent required under Data Protection Laws.

## 7. Allocation of costs

7.1 Each Party shall perform its obligations under this Data Protection Exhibit at its own cost, unless otherwise specified.

## 8. Governing Law

8.1 The governing law of this Data Protection Exhibit shall be the law set forth in the Agreement, except that the governing law for the purposes of Clause 17 of the C-to-C Transfer Clauses shall be as set forth in Appendix 3.

## 9. Termination

9.1 NexTech is entitled to suspend and/or terminate the Agreement in so far as it relates to Personal Data by giving notice to the other Party if:

(a) such other Party commits any material breach of this Agreement; and

(b) NexTech gives notices to such other Party to remedy the breach (or to the extent that the breach is not capable of remedy, to give compensation for it) and the other Party fails to do so within twenty-eight days of the notice.

## 10. Miscellaneous

10.1 In the event of inconsistencies between the provisions of this Data Protection Exhibit and other agreements (including the Agreement) between the Parties, the provisions of this Data Protection Exhibit shall prevail with regard to the Parties' obligations relating to Personal Data. In cases of doubt, this Data Protection Exhibit shall prevail, in particular, where it cannot be clearly established whether a clause relates to a Party's data protection obligations.

10.2 The Parties acknowledge and agree that any NexTech Affiliate acting as a data controller may enforce any of NexTech's rights or the other Party's obligations under this Data Protection Exhibit to the extent such NexTech Affiliate reasonably deems necessary to comply with its obligations under Data Protection Laws.

10.3 Should any provision or condition of this Data Protection Exhibit be held or declared invalid, unlawful or unenforceable by a competent authority or court, then the remainder of this Data Protection Exhibit shall remain valid. Such an invalidity, unlawfulness or unenforceability shall have no effect on the other provisions and conditions of this Data Protection Exhibit to the maximum extent permitted by law. The provision or condition affected shall be construed either:

(a) to be amended in such a way that ensures its validity, lawfulness and enforceability while preserving the Parties' intentions; or if that is not possible,

(b) as if the invalid, unlawful or unenforceable part had never been contained in this Data Protection Exhibit.

10.4 Except as stated in Section 6.1, any amendments to this Data Protection Exhibit shall be in writing duly signed by authorised representatives of the Parties hereto.

10.5 Notwithstanding anything in the Agreement or any order form entered in connection therewith to the contrary, the Parties acknowledge and agree that Seller's access to Personal Data does not constitute part of the consideration exchanged by the Parties in respect of the Agreement.

10.6 Notwithstanding anything to the contrary in the Agreement, any notices required or permitted to be given by Seller to NexTech under this Data Protection Exhibit may be given

(a) in accordance with any notice clause of the Agreement;

(b) to NexTech's primary points of contact with Seller; or

(c) to any email provided by NexTech for the purpose of providing it with Agreement-related communications or alerts.

10.7 In the event of changes to Data Protection Laws, Seller will take, and will ensure its subprocessors take, such measures as required under Data Protection Laws to continue facilitating the lawful processing of Personal Data for the Purposes pursuant to the Agreement, this Data Protection Exhibit, and Data Protection Laws.

10.8 Notwithstanding anything to the contrary in the Agreement, Seller's liability arising from this Data Protection Exhibit shall not be subject to any exclusions or limitations on liability that may be provided for elsewhere in the Agreement.

10.9 Seller will defend NexTech from and against any claims, demands, suits, causes of action, proceedings, investigations or inquiries ("Claims"), and indemnify and hold NexTech harmless from all losses, liabilities, damages, costs and expenses (including reasonable legal fees and fees related to any investigation or regulatory proceeding) ("Losses") to the extent that the Claims or Losses arise out of, are in connection with, or relate to: (i) any breach by Seller of this Data Protection Exhibit; and/or (ii) Seller's violation of any Data Protection Laws.

## Appendix 1 — Details of Processing Activities

This Appendix 1 forms part of the Data Protection Exhibit and also serves as Annex I to the C-to-C Transfer Clauses, as applicable.

**Categories of data subjects whose personal data is transferred:** NexTech customers

**Categories of personal data transferred:** Name; Address; Contact details; Order information; Communication data; Product reviews; The return or refund reasons.

**Sensitive data transferred (if applicable) and applied restrictions or safeguards** that fully take into consideration the nature of the data and the risks involved, such as for instance strict purpose limitation, access restrictions (including access only for staff having followed specialised training), keeping a record of access to the data, restrictions for onward transfers or additional security measures: N/A

**The frequency of the transfer** (e.g. whether the data is transferred on a one-off or continuous basis): Continuous basis

**Nature of the processing:** Receiving data, including accessing; Using data to perform the Services; Sharing data, including disclosure; Erasing data, including destruction and deletion.

**Purpose(s) of the data transfer and further processing:** For the purpose of facilitating the delivery services, product customization services and online instant communication services undertaken by the Seller under the Agreement.

**The duration of the processing and period for which the personal data will be retained**, or, if that is not possible, the criteria used to determine that period: For the duration of the Seller Agreement and operative time of this Data Protection Exhibit.

**A. Competent supervisory authority (where required by Data Protection Laws):** The supervisory authority of the EU Member State where the data exporter is established or has appointed an EU representative. If there is no qualifying EU Member State, the Parties elect the supervisory authority of Ireland.

## Appendix 2 — Technical and Organisational Measures Including Technical and Organisational Measures to Ensure the Security of the Data

This Appendix 2 forms part of the Data Protection Exhibit and also serves as Annex II to the C-to-C Transfer Clauses, to the extent applicable.

1. Organisational management and dedicated staff responsible for the development, implementation and maintenance of Seller's information security program.
2. Audit and risk assessment procedures for the purposes of periodic review and assessment of risks to Seller's organisation, monitoring and maintaining compliance with Seller's policies and procedures, and reporting the condition of its information security and compliance to internal senior management.
3. Data security controls which include, at a minimum, logical segregation of data, restricted (e.g., role-based) access and monitoring, and utilisation of commercially available industry standard encryption technologies for Personal Data that is transmitted over public networks (i.e., the Internet) or when transmitted wirelessly or at rest or stored on portable or removable media (i.e., laptop computers, CD/DVD, USB drives, back-up tapes).
4. Logical access controls designed to manage electronic access to data and system functionality based on authority levels and job functions, (e.g., granting access on a need-to-know and least privilege basis, use of unique IDs and passwords for all users, periodic review and revoking/changing access promptly when employment terminates or changes in job functions occur).
5. Password controls designed to manage and control password strength, expiration and usage including prohibiting users from sharing passwords and requiring that Seller's passwords that are assigned to its employees: (i) be at least eight (8) characters in length, (ii) not be stored in readable format on Seller's computer systems; (iii) must have defined complexity; (iv) must have a history threshold to prevent reuse of recent passwords; and (v) newly issued passwords must be changed after first use.
6. System audit or event logging and related monitoring procedures to proactively record user access and system activity.
7. Physical and environmental security of data centers, server room facilities and other areas containing Personal Data designed to: (i) protect information assets from unauthorised physical access, (ii) manage, monitor and log movement of persons into and out of Seller's facilities, and (iii) guard against environmental hazards such as heat, fire and water damage.
8. Operational procedures and controls to provide for configuration, monitoring and maintenance of technology and information systems, including secure disposal of systems and media to render all information or data contained therein as undecipherable or unrecoverable prior to final disposal or release from Seller's possession.
9. Change management procedures and tracking mechanisms designed to test, approve and monitor all material changes to Seller's technology and information assets.
10. Incident management procedures are designed to allow Seller to investigate, respond to, mitigate and notify of events related to the Seller's technology and information assets.
11. Network security controls that provide for the use of enterprise firewalls and layered DMZ architectures, and intrusion detection systems and other traffic and event correlation procedures designed to protect systems from intrusion and limit the scope of any successful attack.
12. Vulnerability assessment, patch management and threat protection technologies, and scheduled monitoring procedures designed to identify, assess, mitigate and protect against identified security threats, viruses and other malicious code.
13. Business resiliency/continuity and disaster recovery procedures designed to maintain service and/or recovery from foreseeable emergencies or disasters.

For transfers to (sub-) processors, also describe the specific technical and organisational measures to be taken by the (sub-) processor to be able to provide assistance to the controller and, for transfers from a processor to a sub-processor, to the data exporter.

## Appendix 3 — Jurisdiction-Specific Provisions

The terms below shall have the following meanings ascribed to them for the purposes of this Appendix 3:

- "Data Exporter" means the Party transferring Personal Data outside of a country or, where there is no such transfer, the data controller; and
- "Data Importer" means the Party receiving Personal Data subject to direct or onward transfer or, where there is no such transfer, the data processor.

### I. European Economic Area

A. The terms below shall have the following meanings ascribed to them for the purposes of this Section I:

(a) "Europe" means the European Economic Area;

(b) "European Data Protection Laws" means any applicable laws of Europe that relate to the processing of Personal Data under this Agreement.

(c) "GDPR" means Regulation (EU) 2016/679 of the European Parliament and the Council of 27 April 2016.

B. To the extent that any Data Exporter, acting as data controller, transfers Personal Data subject to European Data Protection Laws, either directly or via onward transfer, to a Data Importer, acting as a data controller, located in a country that does not ensure an adequate level of protection within the meaning of European Data Protection Laws, the Parties agree to comply with the terms of the C-to-C Transfer Clauses, which are hereby incorporated into this Data Protection Exhibit by reference.

C. For the purposes of the C-to-C Transfer Clauses, the following additional provisions shall apply:

(a) the names and addresses of those Data Exporter(s) and Data Importer(s) shall be considered to be incorporated into the C-to-C Transfer Clauses;

(b) The Parties' execution of this Data Protection Exhibit shall be considered as signature to the C-to-C Transfer Clauses.

(c) Clause 7 (Docking Clause) shall apply.

(d) The option under Clause 11 (Redress) shall not apply.

(e) For the purposes of paragraph (a) of Clause 13 (Supervision), the Data Exporter shall be considered as established in an EU Member State.

(f) The governing law for the purposes of Clause 17 (Governing law) shall be the law of Ireland.

(g) The courts under Clause 18 (Choice of forum and jurisdiction) shall be the courts of Ireland.

(h) The contents of Appendix 1 shall form Annex I to the C-to-C Transfer Clauses.

(i) The Irish Data Protection Commission shall act as competent supervisory authority for the purposes of Annex I.C of the C-to-C Transfer Clauses (Competent Supervisory Authority).

(j) The contents of Appendix 2 shall form Annex II of the C-to-C Transfer Clauses (Technical and organisational measures including technical and organisational measures to ensure the security of the data).

D. The Parties shall each provide such information to data subjects as is required by GDPR Articles 13 and 14, as relevant, in respect of the Purposes.

E. Seller shall ensure that it complies with GDPR Article 5(1)(e) and, in particular, shall keep Personal Data in a form that permits identification of data subjects for no longer than is necessary for the Purposes.

### II. Japan

A. The following provisions apply to all processing and transfers of Personal Data subject to Data Protection Laws of Japan.

B. For the avoidance of doubt, "Data Protection Laws" includes the Act on the Protection of Personal Information (Act No. 57 of 2003, as amended) ("APPI").

C. Data Importer shall not process Personal Data for purposes other than those specified in Appendix 1, or as otherwise agreed by the Data Exporter and Data Importer (for the purpose of this section, the "Utilization Purposes") without the prior written consent of the Data Exporter. Data Exporter represents that it has notified all applicable data subjects of the Utilization Purposes to the extent required by Data Protection Laws.

D. Data Importer and Data Exporter agree to the collection of consents from data subjects required by Data Protection Laws as set forth in Section 3 of the Data Protection Exhibit, including without limitation for (1) the collection of any "Special Care-Required Personal Information" (as defined by Data Protection Laws) and (2) any disclosures of Personal Data made by Data Exporter to third parties, subject to Clause G below.

E. Data Importer shall keep the Personal Data accurate and up-to-date within the scope necessary to achieve the Utilization Purposes, and shall delete any Personal Data that becomes unnecessary to achieve a Utilization Purpose or other legitimate business purpose. For the avoidance of doubt, it is not necessary to delete Personal Data where applicable laws require the Data Importer to retain it.

F. Data Importer shall have in place appropriate technical and organizational measures to protect the Personal Data against accidental or unlawful destruction or accidental loss, leakage, alteration, and unauthorized disclosure or access, and which provide a level of security appropriate to the risk represented by the processing and the nature of the data to be protected.

G. Data Importer shall exercise the necessary and appropriate control and supervision over its officers, employees, and subprocessors to securely manage the Personal Data received.

H. Data Importer shall not disclose Personal Data to any third party except: (i) where such disclosure, transfer or access is mandated by applicable law; or (ii) where Data Exporter consents to the disclosure of Personal Data to the third party; or (iii) as permitted in Clause H, below. In the event that Data Importer discloses Personal Data to a third party, Data Importer shall impose contractual obligations upon the third party that are no less restrictive than the terms set forth in this Data Protection Exhibit.

I. In the case where Data Importer entrusts the handling of the Personal Data to a third party pursuant to Clause H above, they shall exercise necessary and appropriate control and supervision over the entrustees to ensure the safety of such Personal Data, as stated in Clause G above, and they shall require the entrustees comply with obligations equivalent to the obligations of the Data Importers under this Data Protection Exhibit, including the obligations in this section. The Data Importers shall be responsible for any breach by the entrustees (and any subsequent entrustee) of the obligations above. For clarity, Clause H shall apply to all third-party entrustees and subsequent third-party entrustees.

J. To the extent required by the APPI, upon request of the data subject, each Data Importer shall correct, add, or delete certain Personal Data if the data subject can show the contents of the Personal Data are incorrect. Each Data Importer shall promptly inform the data subject if it has corrected, added, or deleted Personal Data, or if it has determined it does not have to do so.

K. To the extent required by the APPI, upon request of the data subject, each Data Importer shall disclose the information on the Personal Data stipulated under the APPI, including (i) the contents of the retained Personal Data; (ii) the name of the Data Importer; (iii) the Utilization Purposes; (iv) the procedures for responding to a request for the Personal Data; and (v) the contact information data subjects should use to make claims regarding the handling of the Personal Data. Each Data Importer shall promptly inform the data subject if it has determined it does not have to provide requested information on the contents and/or the Utilization Purposes of the Personal Data.

L. To the extent required by the APPI, each Data Importer shall delete or stop utilizing the Personal Data if the data subject can show that the Data Importer is using or has used such Personal Data outside of the designated Utilization Purposes or if was acquired by improper means; provided, however, that it is not required where it would be unreasonably expensive or unreasonably difficult to do so and where alternative action which would protect the data subject's interests can be taken. Each Data Importer shall promptly inform the data subject if it has deleted or stopped utilizing the Personal Data, or if it has determined it does not have to do so.

M. To the extent required by the APPI, each Data Importer shall stop providing Personal Data to a third party, if the Data Importer has provided it to a third party in violation of the restrictions related to the provisions of the Personal Data to a third party under the APPI; provided, however, that it is not required where it would be unreasonably expensive or unreasonably difficult to do so and where alternative action which would protect the data subject's interests can be taken. Each Data Importer shall promptly inform the data subject if it has stopped providing the Personal Data, or if it has determined it does not have to do so.

N. If a Data Importer knows or should know that any Personal Data has been or is likely to be leaked, disclosed, accessed, destroyed, altered, lost, used without authorization, or otherwise handled in any way not permitted under this Data Protection Exhibit, regardless of whether or not the Data Importer is liable for such incidents, the Data Importer shall immediately inform the Data Exporter of the same in writing, and shall take any appropriate measures to prevent such incident from occurring, expanding, and recurring.

### III. Korea

A. The following provisions apply to all processing and transfers of Personal Data subject to applicable laws in Korea. When processing Personal Data provided by or on behalf of Data Exporter:

(a) The scope, classification, purposes and details of the processing of the Personal Data shall be as described in Appendix 1, or as otherwise agreed by the Data Exporter and Data Importer.

(b) Data Importer shall limit access to Personal Data to those personnel who reasonably require such access for the purposes of the processing, and Data Importer shall establish and maintain safeguards as per Appendix 2 of the Data Protection Exhibit, including without limitation any safeguards necessary to comply with rules and regulations of Korean Data Protection Law from time to time (as applicable to an overseas transferee of Personal Data).

(c) Notwithstanding anything in this Data Protection Exhibit to the contrary, Data Importer shall not disclose or transfer to any person or entity any Personal Data unless it obtains prior consent to transfer from relevant data subjects or otherwise does so in accordance with applicable provisions of Korean Data Protection Law.

(d) Data Importer shall establish and implement appropriate procedures for (i) the handling of complaints regarding invasions of privacy and (ii) the resolution of any disputes with data subjects.

(e) Data Importer shall be subject to (i) appropriate training and supervision with respect to its handling of Personal Data, and (ii) supervision and audit by relevant supervisory authorities.

### IV. Switzerland

A. For the purposes of this Section IV, the term "Swiss Data Protection Laws" means Switzerland's Federal Act on Data Protection of June 19, 1992, the Ordinance to the Federal Act on Data Protection, and the Ordinance on Data Protection Certification, and all Swiss laws relating to the processing, privacy, protection, or use of Personal Data.

B. To the extent any Data Exporter transfers Personal Data subject to Swiss Data Protection Laws, either directly or via onward transfer, to a Data Importer located in a country that does not ensure an adequate level of protection within the meaning of Swiss Data Protection Laws, the Parties agree to the C-to-C Transfer Clauses in accordance with Section I of this Appendix 3 as supplemented by Clause C of this Section IV.

C. The following additional provisions shall apply so that the C-to-C Transfer Clauses are suitable for providing an adequate level of protection for such transfer under Swiss Data Protection Laws:

(a) "FDPIC" means the Swiss Federal Data Protection and Information Commissioner.

(b) "Revised FADP" means the revised version of the Federal Act of Data Protection ("FADP") of 25 September 2020, which is scheduled to come into force on 1 January 2023.

(c) The term "EU Member State" must not be interpreted in such a way as to exclude data subjects in Switzerland from the possibility for suing their rights in their place of habitual residence (Switzerland) in accordance with Clause 18(c).

(d) The C-to-C Transfer Clauses also protect the data of legal entities until the entry into force of the Revised FADP.

(e) The FDPIC shall act as the "competent supervisory authority" insofar as the relevant data transfer is governed by the FADP.

### V. United Kingdom

A. The terms below shall have the following meanings ascribed to them for the purposes of this Section:

(a) "UK" means the United Kingdom.

(b) "UK Data Protection Laws" means the UK GDPR, Data Protection Act of 2018, and all UK laws relating to the processing, privacy, protection, or use of Personal Data.

(c) "UK GDPR" means the United Kingdom General Data Protection Regulation, as it forms part of the law of England and Wales, Scotland and Northern Ireland by virtue of section 3 of the European Union (Withdrawal) Act 2018.

B. To the extent any Data Exporter transfers Personal Data subject to UK Data Protection Laws, either directly or via onward transfer, to a Data Importer located in a country that does not ensure an adequate level of protection within the meaning of UK Data Protection Laws, the Parties agree to the C-to-C Transfer Clauses in accordance with Section I of this Appendix 3 as supplemented by Clause C of this Section V.

C. The following additional provisions shall apply so that the C-to-C Transfer Clauses are suitable for providing an adequate level of protection for such transfer under UK Data Protection Laws:

(a) Part 2: Mandatory Clauses of the Approved Addendum, being the template Addendum B.1.0 issued by the ICO and laid before Parliament in accordance with s119A of the Data Protection Act 2018 on 28 January 2022, as it is revised under Section 18 of those Mandatory Clauses ("Approved Addendum") shall apply.

(b) The information required by Part 1 of the Approved Addendum is set out in Appendix 1 of this Data Protection Exhibit.

(c) With respect to Section 19 of the Approved Addendum, in the event the Approved Addendum changes, neither Party may end the Approved Addendum except as provided for in the Approved Addendum or the Agreement.

### VI. United States

A. The following provisions apply to the provision of Personal Data that is subject to Data Protection Laws of the United States (which includes the laws of any state of the United States) ("US Personal Information").

B. To the extent NexTech discloses Deidentified data (as that term is defined under Data Protection Laws) originally derived from US Personal Information to Seller, or to the extent Recipient creates Deidentified data from US Personal Information received from or on behalf of NexTech, Seller shall:

(a) adopt reasonable measures to prevent such Deidentified data from being used to infer information about, or otherwise being associated with, a particular natural person or, where required by Data Protection Laws, a household;

(b) publicly commit to maintain and use such Deidentified data in a deidentified form and to not attempt to re-identify the Deidentified data, except that Seller may attempt to re-identify the data solely for the purpose of determining whether its deidentification processes satisfy the requirements of Data Protection Laws, as applicable; and

(c) contractually obligate any recipients of the Deidentified data, including subprocessors, contractors, and other third parties, to comply with the provisions of this Section.

## Appendix 4 — List of processors

Sellers hereby warrant that they comply with section 5 of this Data Protection Exhibit in respect of selecting and appointing processors. Sellers shall inform NexTech at least 30 days before they formally engage any processors. NexTech then shall have a 30-day right to object to any such engagement upon the receipt of such emails sent by sellers.
MD;
};
