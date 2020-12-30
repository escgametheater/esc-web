import React, { PureComponent } from 'react';
import { Flex, FlexItem, TextInput, Button } from "mineral-ui";
import { withTheme } from "emotion-theming";
import { ReactComponent as SwapHorizontalIcon } from "../icons/ic_swap_horiz.svg";

import "./ColorPicker.css";

const ThemedSwapHorizontalIcon = withTheme(({ theme }) => (
  <SwapHorizontalIcon fill={theme.icon_color} />
));

class ColorPreview extends PureComponent {
  render() {
    const { color } = this.props;

    return (
      <div
        className="color-preview"
        style={{
          backgroundColor: color,
        }}
      />
    );
  }
}

export default class ColorPicker extends PureComponent {
  triggerColorChange = e => {
    if (this.props.onChange) {
      this.props.onChange(e.target.value, e);
    }
  }

  render() {
    const { color, swapWith=false, onSwapClick=()=>{} } = this.props;

    return (
      <Flex className="color-picker" alignItems="center">
        <FlexItem className="color-picker__input">
          <ColorPreview color={color} />
          <input type="color" value={color} onChange={this.triggerColorChange} />
        </FlexItem>
        <FlexItem>
          <TextInput value={color} onChange={this.triggerColorChange} />
        </FlexItem>
        {swapWith && (
          <FlexItem marginLeft="auto" marginRight="auto">
            <Button iconStart={<ThemedSwapHorizontalIcon />} onClick={e => onSwapClick(swapWith)} />
          </FlexItem>
        )}
      </Flex>
    );
  }
}
