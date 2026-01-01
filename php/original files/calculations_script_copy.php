function calculations(){
      var activePopup = $(".modal.show");
      var cost = parseFloat(activePopup.find(".cost").val());
      var pricingCost = parseFloat(activePopup.find(".pricing_cost").val());
      var vatScheme = parseFloat(activePopup.find(".vatScheme option:selected").attr("data-tax_rate"));
      var pricingMethod = activePopup.find(".pricing_method").val();
      var fieldValue = 0;
      var fieldID = '';

      if(!$.isNumeric(cost)) cost = 0;
      if(!$.isNumeric(pricingCost)) pricingCost = 0;
      if(!$.isNumeric(vatScheme)) vatScheme = 0;
            
      $.each(activePopup.find(".calculationField") , function(index , value){
        fieldValue = parseFloat($(value).val());
        if(activePopup.attr("id")=='updateRecordPopup' || activePopup.attr("id")=='productPopup'){
          fieldID = $(value).attr("id");
          fieldID = fieldID.replace("u-", "");
        }else{
          fieldID = $(value).attr("id");
        }
        
        
        if(!$.isNumeric(fieldValue)) fieldValue = 0;
        
        var profit = 0, percent = 0, incVatPrice = 0;
        
        if(fieldID=='fixedRetailPricing' || fieldID=='fixedTradePricing') pricingMethod = 0;
        
        if(fieldID=='retailMarkup' || fieldID=='tradeMarkup') {
          fieldValue = fieldValue/100;
          // profit =IF(pricingmethod=0,retailmarkup*costprice,IF(pricingmethod=1,retailmarkup*pcost,IF(pricingmethod=2,retailmarkup*costprice,0)))
          // inc vat price =IF(pricingmethod=0,(costprice+(costprice*retailmarkup))*(1+taxrate),IF(pricingmethod=1,(pcost+(pcost*retailmarkup))*(1+taxrate),IF(pricingmethod=2,"","")))
          if(pricingMethod==0){
            profit = (fieldValue*cost).toFixed(2) ;
            incVatPrice = ((cost+(cost*fieldValue))*(1+vatScheme)).toFixed(2) ;
            activePopup.find(".incVatPrice").show();
          }else if(pricingMethod==1){
            profit = (fieldValue*pricingCost).toFixed(2) ;
            incVatPrice = ((pricingCost+(pricingCost*fieldValue))*(1+vatScheme)).toFixed(2) ;
            activePopup.find(".incVatPrice").show();
          }else if(pricingMethod==2){
            profit = (fieldValue*cost).toFixed(2) ;
            activePopup.find(".incVatPrice").hide();
          }
        }else{
          // Profit =IF(pricingmethod=0,((targetretail/(1+taxrate))-costprice),IF(pricingmethod=1,((targetretail/(1+taxrate))-pcost),IF(pricingmethod=2,((targetretail/(1+taxrate)-costprice)),0)))
          // percentage =IF(pricingmethod=0,profittargetretail/costprice,IF(pricingmethod=1,profittargetretail/pcost,IF(pricingmethod=2,profittargetretail/costprice,0)))
          if(pricingMethod==0){
            profit = ((fieldValue/(1+vatScheme))-cost).toFixed(2) ;
            percent = ((profit/cost) * 100).toFixed(2) ;
          }else if(pricingMethod==1){
            profit = ((fieldValue/(1+vatScheme))-pricingCost).toFixed(2) ;
            percent = ((profit / pricingCost) * 100).toFixed(2) ;
          }else if(pricingMethod==2){
            profit = ((fieldValue/(1+vatScheme)-cost)).toFixed(2) ;
            percent = ((profit/cost) * 100).toFixed(2) ;
          }
        }
        
        if($.isNumeric(fieldValue) && fieldValue!=0){
          if(fieldID=='targetRetail'){
            activePopup.find(".targetRetailPercent").html(percent);
            activePopup.find(".targetRetailProfit").html(profit); 
          }else if(fieldID=='targetTrade'){
            activePopup.find(".targetTradePercent").html(percent);
            activePopup.find(".targetTradeProfit").html(profit);
          }else if(fieldID=='retailMarkup'){
            activePopup.find(".retailMarkupProfit").html(profit);
            activePopup.find(".retailMarkupIncVatPrice").html(incVatPrice);
            activePopup.find(".retailIncVat").val(incVatPrice);
          }else if(fieldID=='tradeMarkup'){
            activePopup.find(".tradeMarkupProfit").html(profit);
            activePopup.find(".tradeMarkupIncVatPrice").html(incVatPrice);
            activePopup.find(".tradeIncVat").val(incVatPrice);
          }else if(fieldID=='fixedRetailPricing'){
            activePopup.find(".fixedRetailPricingProfit").html(profit);
          }else if(fieldID=='fixedTradePricing'){
            activePopup.find(".fixedTradePricingProfit").html(profit);
          }
        }
        
      })
      
    }

$(document).on('change' , '.doCalculations' , function(e){
      e.preventDefault();
      calculations();
    })
    
    $(document).on('change' , '.pricing_method' , function(e){
      var activePopup = $(".modal.show");
      if($(this).val()==2){
        activePopup.find(".fixedPriceMethod").show();
        activePopup.find(".markupPriceMehtod").hide();
      }else{
        activePopup.find(".markupPriceMehtod").show();
        activePopup.find(".fixedPriceMethod").hide();
      }
    })
